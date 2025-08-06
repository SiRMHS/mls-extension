<?php

/*
Plugin Name: Micro Learn Score
Description: افزودن متاباکس امتیاز میکرولرنینگ به پست تایپ dornalms_course
Version: 1.0
Author: محمد حسن صفره
*/

require_once __DIR__ . '/mls-ranking-table.php';

if (!defined('ABSPATH')) exit;

add_action('add_meta_boxes', function() {
    add_meta_box(
        'micro_learn_score',
        'امتیاز میکرولرنینگ',
        'mls_render_metabox',
        'dornalms_course',
        'normal',
        'high'
    );
});


function mls_render_metabox($post) {
    $mls_nonce = wp_create_nonce('mls_save_score');
    echo "<script>
        const MLS_AJAX = {
            url: '" . admin_url('admin-ajax.php') . "',
            nonce: '$mls_nonce',
            postId: " . (int) $post->ID . "
        };
    </script>";
    $stored = get_post_meta($post->ID, 'mls_score', true);
    echo '<div id="mls-stored-box" style="background:#e3f7e3;border:1px solid #b2e2b2;color:#256029;padding:7px 12px;border-radius:5px;margin-bottom:10px;font-size:15px;font-weight:bold;">امتیاز ذخیره‌شده: <span class="mls-stored">'. esc_html($stored) .'</span> / 100</div>';

    $json = get_post_meta($post->ID, 'dornalms_course_json', true);
    if (!$json) {
        if (preg_match('/<textarea[^>]*name=["\']dornalms_course_json["\'][^>]*>(.*?)<\/textarea>/is', $post->post_content, $m)) {
            $json = trim($m[1]);
        } else {
            global $wp_current_screen;
            if (isset($_POST['dornalms_course_json'])) {
                $json = trim($_POST['dornalms_course_json']);
            } else if (isset($_GET['post'])) {
                $post_id = intval($_GET['post']);
                $post_obj = get_post($post_id);
                if ($post_obj && preg_match('/<textarea[^>]*name=["\']dornalms_course_json["\'][^>]*>(.*?)<\/textarea>/is', $post_obj->post_content, $m2)) {
                    $json = trim($m2[1]);
                }
            }
        }
    }
    echo '<button type="button" id="mls-refresh-btn" class="button button-secondary" style="margin-bottom:10px; display:none;">بروزرسانی امتیاز</button>';
    echo '<div id="mls-score-content">';
    if (!$json) {
        echo '<p>هیچ محتوایی یافت نشد.</p>';
    } else {
        echo '<script>window._mls_json = ' . json_encode($json) . ';</script>';
        echo '<script>window._mls_json_raw = ' . json_encode($json) . ';</script>';
    }
    echo '</div>';
    ?>
    <style>
    .mls-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .mls-box {
        background: #f7f7f7;
        border: 1px solid #e0e0e0;
        border-radius: 5px;
        padding: 6px 10px;
        font-size: 13px;
        min-width: 220px;
        max-width: 260px;
        flex: 1 1 220px;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        margin-bottom: 0;
    }
    .mls-title {
        font-weight: bold;
        margin-bottom: 2px;
        width: 100%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .mls-chapter {
        color: #888;
        font-size: 12px;
        margin-bottom: 2px;
        width: 100%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .mls-progress {
        height: 8px;
        border-radius: 4px;
        background: #eee;
        overflow: hidden;
        margin: 4px 0;
        width: 100%;
    }
    .mls-bar {
        height: 100%;
        display: block;
        transition: width .5s;
    }
    .mls-score {
        font-size: 12px;
        color: #333;
        margin-top: 2px;
        width: 100%;
        text-align: left;
        white-space: nowrap;
    }
    </style>
    
    <script>
    function saveMlsScore(score) {
        fetch(MLS_AJAX.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'mls_save_score',
                _wpnonce: MLS_AJAX.nonce,
                post_id: MLS_AJAX.postId,
                score: score
            })
        })
        .then(r => r.json())
        .then(r => {
            if (r.success) {
                let el = document.querySelector('.mls-stored');
                if (el) el.textContent = r.data;
            } else {
                console.warn('خطا در ذخیره امتیاز', r.data);
            }
        });
    }

    function mls_calc_and_render(jsonStr) {
        let data;
        try { data = JSON.parse(jsonStr); } catch(e) { document.getElementById('mls-score-content').innerHTML = '<p>فرمت JSON معتبر نیست.</p>'; return; }
        if (!Array.isArray(data)) { document.getElementById('mls-score-content').innerHTML = '<p>فرمت JSON معتبر نیست.</p>'; return; }
        let total_score = 0, items = [], hasZero = false;
        data.forEach(chapter => {
            if (!chapter.videos || !Array.isArray(chapter.videos)) return;
            chapter.videos.forEach(video => {
                let duration = parseInt(video.duration || 0);
                if (duration === 0) hasZero = true;
                let score = 1;
                let reason = '';
                if (duration === 0) {
                    score = 1;
                    reason = 'مدت زمان وارد نشده است';
                } else if (duration < 60) {
                    score = 2;
                    reason = 'کمتر از 1 دقیقه (خیلی کم امتیاز)';
                } else if (duration >= 60 && duration < 180) {
                    // 1 تا 3 دقیقه: امتیاز 6 تا 9 بر اساس نزدیکی به 4 دقیقه
                    let ideal = 240; // 4 دقیقه ایده‌آل
                    let diff = Math.abs(duration - ideal);
                    score = Math.round(10 - (diff / 180) * 4); // بین 6 تا 10
                    reason = 'نزدیک به بازه ایده‌آل (امتیاز نسبی)';
                } else if (duration >= 180 && duration <= 420) {
                    // 3 تا 7 دقیقه: هرچه نزدیک‌تر به 4 دقیقه، امتیاز بالاتر
                    let ideal = 240;
                    let diff = Math.abs(duration - ideal);
                    score = Math.round(10 - (diff / 180) * 2); // بین 8 تا 10
                    reason = (diff < 30) ? '' : 'در بازه مناسب (امتیاز نسبی)';
                } else if (duration > 420 && duration <= 600) {
                    // 7 تا 10 دقیقه: امتیاز 7 تا 9
                    let over = duration - 420;
                    score = Math.max(7, 9 - Math.round(over / 60));
                    reason = 'بیشتر از 7 دقیقه (کسر امتیاز)';
                } else if (duration > 600) {
                    score = 5;
                    reason = 'خیلی طولانی (کسر امتیاز زیاد)';
                }
                // اگر دقیقاً ایده‌آل (4 دقیقه)
                if (duration === 240) {
                    score = 10;
                    reason = 'مدت زمان ایده‌آل';
                }
                items.push({
                    chapter: chapter.title,
                    title: video.title,
                    duration: duration,
                    score: score,
                    reason: reason
                });
                total_score += score;
            });
        });
        let max_score = items.length * 10;
        let normalized = max_score ? Math.round(total_score * 100 / max_score) : 0;
        saveMlsScore(normalized);
        if (!items.length) { document.getElementById('mls-score-content').innerHTML = '<p>هیچ ویدیویی یافت نشد.</p>'; return; }
        let html = '';
        if (hasZero) {
            html += '<div style="background:#fff3cd;border:1px solid #ffeeba;color:#856404;padding:8px 12px;border-radius:5px;margin-bottom:10px;font-size:13px;">لطفا تایم ویدیوهای خود را برای محاسبه امتیاز وارد کنید.</div>';
        }
        html += '<div class="mls-grid">';
        function toMinSec(sec) {
            sec = parseInt(sec)||0;
            let m = Math.floor(sec/60), s = sec%60;
            return m+":"+(s<10?"0":"")+s;
        }
        items.forEach(item => {
            let percent = item.score * 10;
            let color = item.score >= 10 ? '#4caf50' : item.score >= 7 ? '#2196f3' : item.score >= 4 ? '#ffc107' : '#f44336';
            html += `<div class="mls-box">
                <div class="mls-title">${item.title}</div>
                <div class="mls-chapter">${item.chapter}</div>
                <div class="mls-progress"><span class="mls-bar" style="width:${percent}%;background:${color};"></span></div>
                <span class="mls-score">زمان: ${toMinSec(item.duration)} | امتیاز: ${item.score}/10</span>`;
            if(item.reason) html += `<div style='color:#d35400;font-size:11px;margin-top:2px;'>${item.reason}</div>`;
            html += `</div>`;
        });
        html += '</div>';
        html += `<p><strong>مجموع امتیاز: ${normalized} / 100</strong></p>`;
        document.getElementById('mls-score-content').innerHTML = html;
    }
    document.addEventListener('DOMContentLoaded', function() {
        let btn = document.getElementById('mls-refresh-btn');
        btn.addEventListener('click', function() {
            let ta = document.querySelector('textarea[name="dornalms_course_json"]');
            if (ta) mls_calc_and_render(ta.value);
        });
        let ta = document.querySelector('textarea[name="dornalms_course_json"]');
        if (ta && ta.value) mls_calc_and_render(ta.value);
    });
    </script>
    <?php
}
add_action('wp_ajax_mls_save_score', 'mls_save_score_cb');
function mls_save_score_cb() {
    check_ajax_referer('mls_save_score');
    $post_id = absint($_POST['post_id'] ?? 0);
    if (!$post_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error('no_permission');
    }
    $score = isset($_POST['score']) ? intval($_POST['score']) : 0;
    $score = max(0, min(100, $score));
    update_post_meta($post_id, 'mls_score', $score);
    wp_send_json_success($score);
}

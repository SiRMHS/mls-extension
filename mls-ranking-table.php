<?php
/*
 * جدول رنکینگ دوره‌ها با شورتکد [mls_ranking]
 */
if (!defined('ABSPATH')) exit;

add_shortcode('mls_ranking', function() {
    $args = array(
        'post_type' => 'dornalms_course',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    );
    $q = new WP_Query($args);
    $rows = [];
    if ($q->have_posts()) {
        foreach ($q->posts as $post) {
            $mls_score = get_post_meta($post->ID, 'mls_score', true);
            $percent = is_numeric($mls_score) ? intval($mls_score) : 0;
            $rows[] = [
                'author' => get_the_author_meta('display_name', $post->post_author),
                'title' => get_the_title($post->ID),
                'score' => $percent,
                'permalink' => get_permalink($post->ID),
            ];
        }
    }
    usort($rows, function($a, $b) { return $b['score'] <=> $a['score']; });
    ob_start();
    echo '<style>
.mls-rank-table{width:100%;border-collapse:separate;border-spacing:0 8px;margin:24px 0;font-size:15px;direction:rtl;text-align:center;font-family:"IY",vazirmatn,sans-serif}
.mls-rank-table th{font-weight:700;padding:10px 8px;border:none;color:#1D3557}
.mls-rank-table td{background:#fff;padding:10px 8px;border:none;vertical-align:middle;color:#1D3557}
.mls-rank-bar{height:14px;border-radius:7px;background:#eee;overflow:hidden;width:90px;display:inline-block;vertical-align:middle;margin-left:8px;box-shadow:0 1px 2px #0001}
.mls-rank-bar-inner{height:100%;display:block;transition:width .5s}
.mls-medal{font-size:22px;vertical-align:middle}
</style>';
    echo '<table class="mls-rank-table">';
    echo '<tr><th>ردیف</th><th>نام استاد</th><th>نام دوره</th><th>امتیاز</th></tr>';
    $i = 1;
    foreach ($rows as $row) {
        echo '<tr>';
        if ($i == 1) {
            $rank = '<span class="mls-medal" title="رتبه ۱">🥇</span>';
        } elseif ($i == 2) {
            $rank = '<span class="mls-medal" title="رتبه ۲">🥈</span>';
        } elseif ($i == 3) {
            $rank = '<span class="mls-medal" title="رتبه ۳">🥉</span>';
        } else {
            $rank = '<span style="font-size:16px;font-weight:600">' . $i . '</span>';
        }
        echo '<td>' . $rank . '</td>';
        echo '<td style="font-weight:500;color:#256029">' . esc_html($row['author']) . '</td>';
        echo '<td style="text-align:right"><a href="' . esc_url($row['permalink']) . '" target="_blank" style="color:#222;text-decoration:none;font-weight:500">' . esc_html($row['title']) . '</a></td>';
        $percent = intval($row['score']);
        $barColor = $percent >= 90 ? '#4caf50' : ($percent >= 70 ? '#2196f3' : ($percent >= 40 ? '#ffc107' : '#f44336'));
        echo '<td><span class="mls-rank-bar"><span class="mls-rank-bar-inner" style="width:' . $percent . '%;background:' . $barColor . '"></span></span> <span style="font-weight:600">' . esc_html($row['score']) . ' / 100</span></td>';
        echo '</tr>';
        $i++;
    }
    echo '</table>';
    return ob_get_clean();
});

<?php
require_once __DIR__ . '/../includes/functions.php';
if (!isAdmin()) { header('Location: login.php'); exit; }

$db = getDb();

// Получить все посты с meta для анализа
$posts = $db->query("SELECT id, title, slug, meta_title, meta_description, content, 
    (SELECT COUNT(*) FROM comments WHERE post_id = posts.id AND status = 'approved') as comments_count,
    (SELECT COUNT(*) FROM post_hashtags WHERE post_id = posts.id) as tags_count,
    likes_count, created_at
    FROM posts WHERE status = 'published' ORDER BY created_at DESC LIMIT 100")->fetchAll();

$total = count($posts);
$good = 0;
$bad = [];

foreach ($posts as $p) {
    $issues = [];
    $score = 100;

    // Meta title
    $titleLen = mb_strlen($p['meta_title'] ?? '');
    if (empty($p['meta_title'])) { $issues[] = 'Нет meta_title'; $score -= 30; }
    elseif ($titleLen < 30) { $issues[] = 'meta_title короткий (' . $titleLen . ' симв.)'; $score -= 10; }
    elseif ($titleLen > 70) { $issues[] = 'meta_title длинный (' . $titleLen . ' симв.)'; $score -= 5; }
    elseif ($titleLen >= 40 && $titleLen <= 60) { $score += 5; } // идеал

    // Meta description
    $descLen = mb_strlen($p['meta_description'] ?? '');
    if (empty($p['meta_description'])) { $issues[] = 'Нет meta_description'; $score -= 30; }
    elseif ($descLen < 80) { $issues[] = 'meta_description короткий (' . $descLen . ' симв.)'; $score -= 10; }
    elseif ($descLen > 170) { $issues[] = 'meta_description длинный (' . $descLen . ' симв.)'; $score -= 5; }

    // Content length
    $contentLen = mb_strlen(strip_tags($p['content'] ?? ''));
    if ($contentLen < 300) { $issues[] = 'Мало текста (' . $contentLen . ' симв.)'; $score -= 20; }
    elseif ($contentLen >= 1000) { $score += 5; }

    // H1 check (заголовок есть)
    if (empty($p['title'])) { $issues[] = 'Нет заголовка (H1)'; $score -= 15; }

    // Теги
    if ($p['tags_count'] == 0) { $issues[] = 'Нет хештегов'; $score -= 5; }

    // Комментарии
    if ($p['comments_count'] == 0) { $issues[] = 'Нет комментариев'; $score -= 5; }
    elseif ($p['comments_count'] >= 3) { $score += 5; }

    // Изображение
    $hasImage = preg_match('/<img[^>]+>/i', $p['content'] ?? '');
    if (!$hasImage) { $issues[] = 'Нет изображений'; $score -= 5; }

    $score = max(0, min(100, $score));
    if ($score >= 70) $good++;

    $bad[] = ['id' => $p['id'], 'title' => $p['title'], 'slug' => $p['slug'], 'score' => $score, 'issues' => $issues, 'meta_title' => $p['meta_title'], 'meta_description' => $p['meta_description']];
}

usort($bad, function($a, $b) { return $a['score'] - $b['score']; });
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Score</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .score-section { background: #1e2a3e; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .score-section h2 { margin-top: 0; }
        .score-stats { display: flex; gap: 20px; margin-bottom: 20px; }
        .score-stat { padding: 16px; border-radius: 8px; min-width: 120px; text-align: center; }
        .score-stat-value { font-size: 28px; font-weight: bold; }
        .score-stat-label { font-size: 12px; margin-top: 4px; }
        .score-green { background: rgba(76,175,80,0.15); }
        .score-green .score-stat-value { color: #4caf50; }
        .score-yellow { background: rgba(255,193,7,0.15); }
        .score-yellow .score-stat-value { color: #ffc107; }
        .score-red { background: rgba(244,67,54,0.15); }
        .score-red .score-stat-value { color: #f44336; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #2a3650; padding: 8px 10px; text-align: left; font-size: 11px; text-transform: uppercase; color: #8a9bd5; }
        td { padding: 8px 10px; border-bottom: 1px solid #2a3650; }
        tr:hover td { background: #1a2640; }
        .score-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-weight: bold; font-size: 13px; }
        .score-good { background: #4caf5033; color: #4caf50; }
        .score-ok { background: #ffc10733; color: #ffc107; }
        .score-bad { background: #f4433633; color: #f44336; }
        .issue-list { list-style: none; padding: 0; margin: 0; font-size: 11px; }
        .issue-list li { color: #f44336; padding: 1px 0; }
    </style>
</head>
<body class="admin">
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <h1>📊 SEO Score</h1>

    <div class="score-section">
        <div class="score-stats">
            <div class="score-stat <?php echo $good/$total > 0.7 ? 'score-green' : ($good/$total > 0.4 ? 'score-yellow' : 'score-red'); ?>">
                <div class="score-stat-value"><?php echo round($good / max($total, 1) * 100); ?>%</div>
                <div class="score-stat-label">Здоровых постов</div>
            </div>
            <div class="score-stat score-green">
                <div class="score-stat-value"><?php echo $good; ?>/<?php echo $total; ?></div>
                <div class="score-stat-label">Постов с score ≥ 70</div>
            </div>
            <div class="score-stat <?php echo $total - $good > 5 ? 'score-red' : 'score-green'; ?>">
                <div class="score-stat-value"><?php echo $total - $good; ?></div>
                <div class="score-stat-label">Нуждаютcя в доработке</div>
            </div>
        </div>

        <table>
            <tr>
                <th>Score</th>
                <th>Пост</th>
                <th>Meta Title</th>
                <th>Meta Desc</th>
                <th>Проблемы</th>
            </tr>
            <?php foreach ($bad as $p): ?>
            <tr>
                <td>
                    <span class="score-badge <?php echo $p['score'] >= 70 ? 'score-good' : ($p['score'] >= 40 ? 'score-ok' : 'score-bad'); ?>">
                        <?php echo $p['score']; ?>
                    </span>
                </td>
                <td><a href="../post/<?php echo h($p['slug']); ?>" target="_blank" style="color:#4a8cff;"><?php echo h(mb_substr($p['title'], 0, 50)); ?></a></td>
                <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;"><?php echo h(mb_substr($p['meta_title'] ?? '', 0, 40)) ?: '<span style="color:#f44336;">(пусто)</span>'; ?></td>
                <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;"><?php echo h(mb_substr($p['meta_description'] ?? '', 0, 40)) ?: '<span style="color:#f44336;">(пусто)</span>'; ?></td>
                <td>
                    <ul class="issue-list">
                        <?php foreach (array_slice($p['issues'], 0, 4) as $issue): ?>
                            <li>• <?php echo h($issue); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>

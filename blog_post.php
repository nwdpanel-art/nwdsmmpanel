<?php

require 'config.php';


/*
|--------------------------------------------------------------------------
| GET POST BY SLUG
|--------------------------------------------------------------------------
*/

$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    header('Location: blog.php');
    exit;
}

$stmt = $db->prepare("
    SELECT b.*, a.email AS author_email
    FROM blog_posts b
    LEFT JOIN admins a ON a.id = b.admin_id
    WHERE b.slug = ? AND b.status = 'published'
    LIMIT 1
");

$stmt->bind_param("s", $slug);
$stmt->execute();

$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    header('Location: blog.php');
    exit;
}

?>

<!doctype html>
<html>
<head>
<?php include __DIR__ . "/theme_init.php"; ?>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($post['title']) ?> - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css">
<style>
.post-wrap {
    max-width: 760px;
    margin: 0 auto;
    padding: 60px 20px;
}
.post-back {
    color: var(--muted);
    text-decoration: none;
    font-size: 14px;
}
.post-title {
    color: var(--text);
    margin: 20px 0 10px;
}
.post-meta {
    color: var(--muted-2);
    font-size: 13px;
    margin-bottom: 25px;
}
.post-image {
    width: 100%;
    max-height: 400px;
    object-fit: cover;
    border-radius: 12px;
    margin-bottom: 30px;
}
.post-content {
    color: var(--text);
    font-size: 16px;
    line-height: 1.8;
}
.post-content img {
    max-width: 100%;
    border-radius: 8px;
    margin: 15px 0;
}
.post-content a {
    color: var(--accent-light);
}
</style>
</head>
<body>

<div class="landing">

<div class="brand" style="padding:20px 20px 0;">
<a href="index.php" style="color:inherit; text-decoration:none;">
<?= e(SITE_NAME) ?>
</a>
</div>

<div class="post-wrap">

<a class="post-back" href="blog.php">&larr; Back to Blog</a>

<h1 class="post-title"><?= e($post['title']) ?></h1>

<div class="post-meta">
<?= date('d M Y', strtotime($post['created_at'])) ?>
<?php if ($post['author_email']): ?>
&nbsp;&middot;&nbsp; by <?= e($post['author_email']) ?>
<?php endif; ?>
</div>

<?php if ($post['featured_image']): ?>
<img
    class="post-image"
    src="uploads/blog/<?= e($post['featured_image']) ?>"
    alt="<?= e($post['title']) ?>"
>
<?php endif; ?>

<div class="post-content">
<?= $post['content'] ?>
</div>

</div>

</div>

<?php include __DIR__ . '/whatsapp_button.php'; ?>

<?php include __DIR__ . "/theme_toggle.php"; ?>
</body>
</html>
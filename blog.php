<?php

require '../config.php';
admin();

$message = '';
$error = '';


/*
|--------------------------------------------------------------------------
| SLUG HELPER
|--------------------------------------------------------------------------
*/

function make_slug($text)
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'post';
}


/*
|--------------------------------------------------------------------------
| CREATE / UPDATE POST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_post'])
) {

    $post_id = (int) ($_POST['post_id'] ?? 0);
    $title   = trim($_POST['title'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $status  = ($_POST['status'] ?? 'draft') === 'published'
        ? 'published'
        : 'draft';

    if ($title === '' || $content === '') {

        $error = 'Title and content are required.';

    } else {

        $slug = make_slug($title);


        /*
        |--------------------------------------------------------------------------
        | ENSURE SLUG IS UNIQUE (append -2, -3... if taken by another post)
        |--------------------------------------------------------------------------
        */

        $base_slug = $slug;
        $suffix    = 2;

        while (true) {

            $check = $db->prepare("
                SELECT id FROM blog_posts
                WHERE slug = ? AND id != ?
            ");

            $check->bind_param("si", $slug, $post_id);
            $check->execute();

            if ($check->get_result()->num_rows === 0) {
                break;
            }

            $slug = $base_slug . '-' . $suffix;
            $suffix++;
        }


        /*
        |--------------------------------------------------------------------------
        | FEATURED IMAGE UPLOAD (OPTIONAL)
        |--------------------------------------------------------------------------
        */

        $featured_image = $_POST['existing_image'] ?? null;

        if (
            isset($_FILES['featured_image']) &&
            $_FILES['featured_image']['error'] === UPLOAD_ERR_OK
        ) {

            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            $extension = strtolower(
                pathinfo(
                    $_FILES['featured_image']['name'],
                    PATHINFO_EXTENSION
                )
            );

            if (!in_array($extension, $allowed)) {

                $error = 'Featured image must be JPG, PNG or WEBP.';

            } elseif ($_FILES['featured_image']['size'] > 5 * 1024 * 1024) {

                $error = 'Featured image must be smaller than 5MB.';

            } else {

                $upload_dir = __DIR__ . '/../uploads/blog/';

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $filename =
                    'blog_' .
                    time() . '_' .
                    bin2hex(random_bytes(4)) .
                    '.' .
                    $extension;

                if (
                    move_uploaded_file(
                        $_FILES['featured_image']['tmp_name'],
                        $upload_dir . $filename
                    )
                ) {
                    $featured_image = $filename;
                }
            }
        }

        if ($error === '') {

            if ($post_id > 0) {

                $stmt = $db->prepare("
                    UPDATE blog_posts
                    SET title = ?,
                        slug = ?,
                        excerpt = ?,
                        content = ?,
                        featured_image = ?,
                        status = ?
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "ssssssi",
                    $title,
                    $slug,
                    $excerpt,
                    $content,
                    $featured_image,
                    $status,
                    $post_id
                );

                $stmt->execute();

                $message = 'Post updated.';

            } else {

                $admin_id = (int) $_SESSION['admin_id'];

                $stmt = $db->prepare("
                    INSERT INTO blog_posts
                    (title, slug, excerpt, content, featured_image, status, admin_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "ssssssi",
                    $title,
                    $slug,
                    $excerpt,
                    $content,
                    $featured_image,
                    $status,
                    $admin_id
                );

                $stmt->execute();

                $message = 'Post created.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| DELETE POST
|--------------------------------------------------------------------------
*/

if (isset($_GET['delete'])) {

    $delete_id = (int) $_GET['delete'];

    $stmt = $db->prepare("
        DELETE FROM blog_posts WHERE id = ?
    ");

    $stmt->bind_param("i", $delete_id);
    $stmt->execute();

    $message = 'Post deleted.';
}


/*
|--------------------------------------------------------------------------
| LOAD POST FOR EDITING
|--------------------------------------------------------------------------
*/

$editing_post = null;

if (isset($_GET['edit'])) {

    $edit_id = (int) $_GET['edit'];

    $stmt = $db->prepare("
        SELECT * FROM blog_posts WHERE id = ?
    ");

    $stmt->bind_param("i", $edit_id);
    $stmt->execute();

    $editing_post = $stmt->get_result()->fetch_assoc();
}


/*
|--------------------------------------------------------------------------
| FETCH ALL POSTS
|--------------------------------------------------------------------------
*/

$posts = $db->query("
    SELECT id, title, slug, status, created_at
    FROM blog_posts
    ORDER BY created_at DESC
");

?>

<!doctype html>
<html>
<head>
<?php include __DIR__ . "/../theme_init.php"; ?>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Blog Management</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="app">

<?php include __DIR__ . "/_sidebar.php"; ?>

<main class="main">

<p class="eyebrow">CONTENT</p>
<h1>Blog Management</h1>

<?php if ($message): ?>
<div class="alert-success"><?= e($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert-error"><?= e($error) ?></div>
<?php endif; ?>


<!-- CREATE / EDIT FORM -->

<div class="card" style="margin-top:15px;">

<div class="card-head">
<h2><?= $editing_post ? 'Edit Post' : 'New Post' ?></h2>
</div>

<form method="POST" enctype="multipart/form-data">

<input type="hidden" name="post_id" value="<?= (int) ($editing_post['id'] ?? 0) ?>">

<?php if ($editing_post && $editing_post['featured_image']): ?>
<input type="hidden" name="existing_image" value="<?= e($editing_post['featured_image']) ?>">
<?php endif; ?>

<div class="form-group">
<label>Title</label>
<input
    class="form-control"
    type="text"
    name="title"
    value="<?= e($editing_post['title'] ?? '') ?>"
    required
>
</div>

<div class="form-group">
<label>Excerpt (short summary, shown on the blog listing)</label>
<input
    class="form-control"
    type="text"
    name="excerpt"
    maxlength="500"
    value="<?= e($editing_post['excerpt'] ?? '') ?>"
>
</div>

<div class="form-group">
<label>Content</label>
<textarea
    class="form-control"
    name="content"
    rows="14"
    required
><?= e($editing_post['content'] ?? '') ?></textarea>
<small style="color:var(--muted);">
Basic HTML is allowed (e.g. &lt;b&gt;, &lt;i&gt;, &lt;p&gt;, &lt;a&gt;, &lt;img&gt;).
</small>
</div>

<div class="form-group">
<label>Featured Image (optional)</label>
<input
    class="form-control"
    type="file"
    name="featured_image"
    accept=".jpg,.jpeg,.png,.webp"
>

<?php if ($editing_post && $editing_post['featured_image']): ?>
<div style="margin-top:10px;">
<img
    src="../uploads/blog/<?= e($editing_post['featured_image']) ?>"
    style="max-width:200px; border-radius:8px;"
>
</div>
<?php endif; ?>

</div>

<div class="form-group">
<label>Status</label>
<select class="form-control" name="status">
<option value="draft" <?= (($editing_post['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Draft</option>
<option value="published" <?= (($editing_post['status'] ?? '') === 'published') ? 'selected' : '' ?>>Published</option>
</select>
</div>

<button class="btn primary" type="submit" name="save_post">
<?= $editing_post ? 'Update Post' : 'Create Post' ?>
</button>

<?php if ($editing_post): ?>
<a href="blog.php" class="btn ghost">Cancel</a>
<?php endif; ?>

</form>

</div>


<!-- POST LIST -->

<div class="card" style="margin-top:20px;">

<div class="card-head">
<h2>All Posts</h2>
</div>

<div class="table-wrap">

<table class="service-table">
<thead>
<tr>
<th>Title</th>
<th>Slug</th>
<th>Status</th>
<th>Created</th>
<th>Actions</th>
</tr>
</thead>
<tbody>

<?php while ($post = $posts->fetch_assoc()): ?>

<tr>

<td><?= e($post['title']) ?></td>
<td><?= e($post['slug']) ?></td>

<td>
<?php if ($post['status'] === 'published'): ?>
<span class="status-active">Published</span>
<?php else: ?>
<span class="status-inactive">Draft</span>
<?php endif; ?>
</td>

<td><?= date('d M Y', strtotime($post['created_at'])) ?></td>

<td>
<a href="blog.php?edit=<?= (int) $post['id'] ?>">Edit</a>
&nbsp;|&nbsp;
<a
    href="blog.php?delete=<?= (int) $post['id'] ?>"
    onclick="return confirm('Delete this post permanently?');"
    style="color:var(--error-text);"
>Delete</a>
&nbsp;|&nbsp;
<a href="../blog_post.php?slug=<?= e($post['slug']) ?>" target="_blank">View</a>
</td>

</tr>

<?php endwhile; ?>

</tbody>
</table>

</div>

</div>

</main>

</div>


</body>
</html>
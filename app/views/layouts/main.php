<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="base-url"   content="<?= url('') ?>">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<title><?php e($page_title ?? WEBSITE_NAME) ?></title>
<?= yield_section('head') ?>
</head>
<body>
<?= view_content() ?>
<?= yield_section('scripts') ?>
<script src="<?= asset('js/noclass.js') ?>"></script>
</body>
</html>

<?php echo '<?';?>xml version="1.0" encoding="UTF-8"?> <!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<title><?php echo section('title'); ?></title>
		<?php echo section('styles'); ?>
		<?php echo section('scripts'); ?>
		<?php echo section('search'); ?>
		<?php echo section('feeds'); ?>
		<?php echo section('description'); ?>
		<?php echo section('head'); ?>
		</head>
	<body id="<?php echo section('action'); ?>">
		<div id="wrap">
			<div id="header">
				<?php echo section('logo'); ?>
				<?php echo section('nav'); ?>
				<?php echo section('notice'); ?>
				<?php echo section('noticeform'); ?>
			</div>
			<div id="core">
				<?php echo section('localnav'); ?>
				<?php echo section('bodytext'); ?>
				<div id="aside_primary" class="aside">
					<?php echo section('export'); ?>
					<?php echo section('subscriptions'); ?>
					<?php echo section('subscribers'); ?>
					<?php echo section('groups'); ?>
					<?php echo section('statistics'); ?>
					<?php echo section('cloud'); ?>
					<?php echo section('groupmembers'); ?>
					<?php echo section('groupstatistics'); ?>
					<?php echo section('groupcloud'); ?>
					<?php echo section('popular'); ?>
					<?php echo section('groupsbyposts'); ?>
					<?php echo section('featuredusers'); ?>
					<?php echo section('groupsbymembers'); ?>
					</div>
				</div>
			<div id="footer">
				<?php echo section('secondarynav'); ?>
				<?php echo section('licenses'); ?>
			</div>
			</div>
		</body>
	</html>

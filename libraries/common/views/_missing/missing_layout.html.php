<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */
?>
<!doctype html>
<html>
<head>
	<?php echo $this->html->charset();?>
	<title>Application > <?php echo $this->title(); ?></title>
	<?php echo $this->html->style(array('debug', 'lithium')); ?>
	<?php echo $this->scripts(); ?>
	<?php echo $this->html->link('Icon', null, array('type' => 'icon')); ?>
</head>
<body class="app">
	<div id="container">
		<div id="header">
			<h1>Minerva CMS</h1>
			<h2>
				Powered by <?php echo $this->html->link('Lithium', 'http://li3.rad-dev.org'); ?>.
			</h2>
		</div>
		<div id="content">
			<div id="missing_layout" class="test-result test-result-fail error">
                            Error: A layout template seems to be missing.
                        </div>
			<?php echo $this->content(); ?>
		</div>
	</div>
	<?=$this->flashMessage->output(); ?>
</body>
</html>
<?
function main()
{
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        handlePost();
    } else {
        showForm();
    }
}

function showForm()
{
?>
<p>Enter your database connection information below to initialize the database.</p>
<form method='post'>
	<fieldset>
	<ul class='form_data'>
	<li>
	<label for='sitename'>Site name</label>
	<input type='text' id='sitename' name='sitename' />
	<p>The name of your site</p>
	</li>
	<li>
	<li>
	<label for='host'>Database host</label>
	<input type='text' id='host' name='host' />
	<p>Database hostname</p>
	</li>
	<li>
	<li>
	<label for='username'>Username</label>
	<input type='text' id='username' name='username' />
	<p>Database username</p>
	</li>
	<li>
	<label for='password'>Password</label>
	<input type='password' id='password' name='password' />
	<p>Database password</p>
	</li>
	</ul>
	<input type='submit' name='submit' value='Submit'>
	</fieldset>
</form>
<?
}

function handlePost()
{
}
?>
<html>
<head>
	<title>Install Laconica</title>
	<link rel="stylesheet" type="text/css" href="theme/base/css/display.css?version=0.7.1" media="screen, projection, tv"/>
	<link rel="stylesheet" type="text/css" href="theme/base/css/modal.css?version=0.7.1" media="screen, projection, tv"/>
	<link rel="stylesheet" type="text/css" href="theme/default/css/display.css?version=0.7.1" media="screen, projection, tv"/>
</head>
<body>
	<div id="wrap">
	<div id="core">
	<div id="content">
	<h1>Install Laconica</h1>
<? main() ?>
	</div>
	</div>
	</div>
</body>
</html>
<?php

set_time_limit(60);
chdir(dirname(__FILE__) . '/..');

/* Languages to pull */
$languages = array(
	'da_DK' => 'http://laconi.ca/translate/download.php?file_id=93',
	'nl_NL' => 'http://laconi.ca/translate/download.php?file_id=97',
	'en_NZ' => 'http://laconi.ca/translate/download.php?file_id=87',
	'eo'    => 'http://laconi.ca/translate/download.php?file_id=88',
	'fr_FR' => 'http://laconi.ca/translate/download.php?file_id=99',
	'de_DE' => 'http://laconi.ca/translate/download.php?file_id=100',
	'it_IT' => 'http://laconi.ca/translate/download.php?file_id=101',
	'ko'    => 'http://laconi.ca/translate/download.php?file_id=102',
	'no_NB' => 'http://laconi.ca/translate/download.php?file_id=104',
	'pt'    => 'http://laconi.ca/translate/download.php?file_id=106',
	'pt_BR' => 'http://laconi.ca/translate/download.php?file_id=107',
	'ru_RU' => 'http://laconi.ca/translate/download.php?file_id=109',
	'es'    => 'http://laconi.ca/translate/download.php?file_id=110',
	'tr_TR' => 'http://laconi.ca/translate/download.php?file_id=114',
	'uk_UA' => 'http://laconi.ca/translate/download.php?file_id=115',
	'he_IL' => 'http://laconi.ca/translate/download.php?file_id=116',
	'mk_MK' => 'http://laconi.ca/translate/download.php?file_id=103',
	'ja_JP' => 'http://laconi.ca/translate/download.php?file_id=117',
	'cs_CZ' => 'http://laconi.ca/translate/download.php?file_id=96',
	'ca_ES' => 'http://laconi.ca/translate/download.php?file_id=95',
	'pl_PL' => 'http://laconi.ca/translate/download.php?file_id=105',
	'sv_SE' => 'http://laconi.ca/translate/download.php?file_id=128'
);

/* Update the languages */
foreach ($languages as $code => $file) {

	$lcdir='locale/'.$code;
	$msgdir=$lcdir.'/LC_MESSAGES';
	$pofile=$msgdir.'/laconica.po';
	$mofile=$msgdir.'/laconica.mo';

	/* Check for an existing */
	if (!is_dir($msgdir)) {
		mkdir($lcdir);
		mkdir($msgdir);
		$existingSHA1 = '';
	} else {
		$existingSHA1 = file_exists($pofile) ? sha1_file($pofile) : '';
	}

	/* Get the remote one */
	$newFile = file_get_contents($file);

	// Update if the local .po file is different to the one downloaded, or
	// if the .mo file is not present.
	if(sha1($newFile)!=$existingSHA1 || !file_exists($mofile)) {
		echo "Updating ".$code."\n";
		file_put_contents($pofile, $newFile);
		$prevdir = getcwd();
		chdir($msgdir);
		system('msgmerge -U laconica.po ../../laconica.pot');
		system('msgfmt -f -o laconica.mo laconica.po');
		chdir($prevdir);
	} else {
		echo "Unchanged - ".$code."\n";
	}
}
echo "Finished\n";

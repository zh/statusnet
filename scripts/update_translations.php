<?php

set_time_limit(60);
chdir(dirname(__FILE__) . '/..');

/* Languages to pull */
$languages = array(
	'da_DK' => 'http://laconi.ca/translate/download.php?file_id=23',
	'nl_NL' => 'http://laconi.ca/translate/download.php?file_id=39',
	'en_NZ' => 'http://laconi.ca/translate/download.php?file_id=15',
	'eo'    => 'http://laconi.ca/translate/download.php?file_id=10',
	'fr_FR' => 'http://laconi.ca/translate/download.php?file_id=19',
	'de_DE' => 'http://laconi.ca/translate/download.php?file_id=18',
	'it_IT' => 'http://laconi.ca/translate/download.php?file_id=21',
	'ko'    => 'http://laconi.ca/translate/download.php?file_id=33',
	'no_NB' => 'http://laconi.ca/translate/download.php?file_id=31',
	'pt'    => 'http://laconi.ca/translate/download.php?file_id=8',
	'pt_BR' => 'http://laconi.ca/translate/download.php?file_id=11',
	'ru_RU' => 'http://laconi.ca/translate/download.php?file_id=26',
	'es'    => 'http://laconi.ca/translate/download.php?file_id=9',
	'tr_TR' => 'http://laconi.ca/translate/download.php?file_id=37',
	'uk_UA' => 'http://laconi.ca/translate/download.php?file_id=44',
	'he_IL' => 'http://laconi.ca/translate/download.php?file_id=71',
	'mk_MK' => 'http://laconi.ca/translate/download.php?file_id=67',
	'ja_JP' => 'http://laconi.ca/translate/download.php?file_id=43',
	'cs_CZ' => 'http://laconi.ca/translate/download.php?file_id=63',
	'ca_ES' => 'http://laconi.ca/translate/download.php?file_id=49',
	'pl_PL' => 'http://laconi.ca/translate/download.php?file_id=51',
	'sv_SE' => 'http://laconi.ca/translate/download.php?file_id=55'
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

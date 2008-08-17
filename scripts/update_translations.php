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
				                   'uk_UA' => 'http://laconi.ca/translate/download.php?file_id=27',
				                   );

/* Update the languages */
foreach ($languages as $code => $file) {
	    /* Check for an existing */
	    if (!is_dir('locale/' . $code)) {
			        mkdir('locale/' . $code);
			        mkdir('locale/' . $code . '/LC_MESSAGES');
			        $existingSHA1 = '';
		} else {
			$existingSHA1 = file_exists('locale/' . $code . '/LC_MESSAGES/laconica.po') ? sha1_file('locale/' . $code . '/LC_MESSAGES/laconica.po') : '';
		}
	
	    /* Get the remote one */
	    $newFile = file_get_contents($file);
	
	    /* Are the different? */
	    if (sha1($newFile) != $existingSHA1) {
			        /* Yes, update */
			        file_put_contents('locale/' . $code . '/LC_MESSAGES/laconica.po', $newFile);
			        $prevdir = getcwd();
			        chdir('locale/' . $code . '/LC_MESSAGES/');
			        system('msgmerge -U laconica.po ../../laconica.pot');
			        system('msgfmt -f -o laconica.mo laconica.po');
			        chdir($prevdir);
		}
}


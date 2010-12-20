<?php

class DeliciousBackupImporter
{
	function importBookmarks($user, $body)
	{
		$doc = $this->importHTML($body);

		$dls = $doc->getElementsByTagName('dl');

		if ($dls->length != 1) {
			throw new ClientException(_("Bad import file."));
		}

		$dl = $dls->item(0);

		$children = $dl->childNodes;

		common_debug("<dl> child nodes is " . $children->length);

		$dt = null;

		for ($i = 0; $i < $children->length; $i++) {
			try {
				$child = $children->item($i);
				if ($child->nodeType != XML_ELEMENT_NODE) {
					continue;
				}
				common_log(LOG_INFO, $child->tagName);
				switch (strtolower($child->tagName)) {
				case 'dt':
					if (!empty($dt)) {
						// No DD provided
						$this->importBookmark($user, $dt);
						$dt = null;
					}
					$dt = $child;
					break;
				case 'dd':
					$dd = $child;
					$saved = $this->importBookmark($user, $dt, $dd);
					$dt = null;
				case 'p':
					common_log(LOG_INFO, 'Skipping the <p> in the <dl>.');
					break;
				default:
					common_log(LOG_WARNING, "Unexpected element $child->tagName found in import.");
				}
			} catch (Exception $e) {
				common_log(LOG_ERR, $e->getMessage());
			}
		}
	}

	function importBookmark($user, $dt, $dd = null)
	{
		common_debug("DT child nodes length = " . $dt->childNodes->length);

		for ($i = 0; $i < $dt->childNodes->length; $i++) {
			$child = $dt->childNodes->item($i);
			if ($child->nodeType == XML_ELEMENT_NODE) {
				common_debug('DT has an element child with tag name '. $child->tagName);
			}
		}

		$as = $dt->getElementsByTagName('a');

		if ($as->length == 0) {
			throw new ClientException(_("No <A> tag in a <DT>."));
		}

		$a = $as->item(0);
					
		$private = $a->getAttribute('private');

		if ($private != 0) {
			throw new ClientException(_('Skipping private bookmark.'));
		}

		if (!empty($dd)) {
			$description = $dd->nodeValue;
		} else {
			$description = null;
		}

		$title       = $a->getAttribute('title');
		$url         = $a->getAttribute('href');
		$tags        = $a->getAttribute('tags');
		$addDate     = $a->getAttribute('add_date');
		$created     = common_sql_date(intval($addDate));

		$saved = Notice_bookmark::saveNew($user,
										  $title,
										  $url,
										  $tags,
										  $description,
										  array('created' => $created));

		return $saved;
	}

	function importHTML($body)
	{
        // DOMDocument::loadHTML may throw warnings on unrecognized elements,
        // and notices on unrecognized namespaces.
        $old = error_reporting(error_reporting() & ~(E_WARNING | E_NOTICE));
        $dom = new DOMDocument();
        $ok = $dom->loadHTML($body);
        error_reporting($old);

		if ($ok) {
			return $dom;
		} else {
			return null;
		}
	}
}

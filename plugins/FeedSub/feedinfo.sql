CREATE TABLE `feedinfo` (
  `id` int(11) NOT NULL auto_increment,
  `profile_id` int(11) NOT NULL,
  `feeduri` varchar(255) NOT NULL,
  `homeuri` varchar(255) NOT NULL,
  `huburi` varchar(255) NOT NULL,
  `verify_token` varchar(32) default NULL,
  `sub_start` datetime default NULL,
  `sub_end` datetime default NULL,
  `created` datetime NOT NULL,
  `lastupdate` datetime NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `feedinfo_feeduri_idx` (`feeduri`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

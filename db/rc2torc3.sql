create table user_location_prefs (
    user_id integer not null comment 'user who has the preference' references user (id),
    share_location tinyint default 1 comment 'Whether to share location data',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    constraint primary key (user_id)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table inbox (

    user_id integer not null comment 'user receiving the notice' references user (id),
    notice_ids blob comment 'packed list of notice ids',

    constraint primary key (user_id)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

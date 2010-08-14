<?php

/* local and remote users have profiles */

$schema['profile'] = array(
    'fields' => array(
        'id' => array('type' => 'serial', 'not null' => true, 'description' => 'unique identifier'),
        'nickname' => array('type' => 'varchar', 'length' => 64, 'not null' => true, 'description' => 'nickname or username'),
        'fullname' => array('type' => 'varchar', 'length' => 255, 'description' => 'display name'),
        'profileurl' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL, cached so we dont regenerate'),
        'homepage' => array('type' => 'varchar', 'length' => 255, 'description' => 'identifying URL'),
        'bio' => array('type' => 'text', 'description' => 'descriptive biography'),
        'location' => array('type' => 'varchar', 'length' => 255, 'description' => 'physical location'),
        'lat' => array('type' => 'decimal', 'length' => '10,7', 'description' => 'latitude'),
        'lon' => array('type' => 'decimal', 'length => '10,7', 'description' => 'longitude'),
        'location_id' => array('type' => 'int', 'description' => 'location id if possible'),
        'location_ns' => array('type' => 'int', 'description' => 'namespace for location'),

        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
    ),
    'primary key' => array('id'),
    'indexes' => array(
        'profile_nickname_idx' => array('nickname'),
        'FULLTEXT' => array('nickname', 'fullname', 'location', 'bio', 'homepage') // ??
    ),
// Any way to specify that this table needs to be myisam if using the fulltext?
);

$schema['avatar'] = array(
    'fields' => array(
        'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to profile table',
        'original' => array('type' => 'boolean', 'default' => false, 'description' => 'uploaded by user or generated?'),
        'width' => array('type' => 'int', 'not null' => true, 'description' => 'image width'),
        'height' => array('type' => 'int', 'not null' => true, 'description' => 'image height'),
        'mediatype' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'file type'),
        'filename' => array('type' => 'varchar', 'length' => 255, 'description' => 'local filename, if local'),
        'url' => array('type' => 'varchar', 'length' => 255, 'description' => 'avatar location'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
    ),
    'primary key' => array('profile_id', 'width', 'height'),
    'unique keys' => array(
        'avatar_url_idx' => array('url'),
    ),
    'foreign keys' => array(
        'profile_id' => array('profile' => 'id'),
    ),
    'indexes' => array(
        'avatar_profile_id_idx' => array('profile_id'),
    ),
);

$schema['sms_carrier'] = array(
    'fields' => array(
        'id' => array('type' => 'int', 'description' => 'primary key for SMS carrier'),
        'name' => array('type' => 'varchar', 'length' => 64, 'description' => 'name of the carrier'),
        'email_pattern' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'sprintf pattern for making an email address from a phone number'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
    ),
    'primary key' => array('id'),
    'unique keys' => array(
        'sms_carrier_name_idx' => array('name'),
    ),
);

$schema['user'] = array(
    'description' => 'local users',
    'fields' => array(
        'id' => array('type' => 'int', 'description' => 'foreign key to profile table'),
        'nickname' => array('type' => 'varchar', 'length' => 64, 'description' => 'nickname or username, duped in profile'),
        'password' => array('type' => 'varchar', 'length' => 255, 'description' => 'salted password, can be null for OpenID users'),
        'email' => array('type' => 'varchar', 'length' => 255, 'description' => 'email address for password recovery etc.'),
        'incomingemail' => array('type' => 'varchar', 'length' => 255, 'description' => 'email address for post-by-email'),
        'emailnotifysub' => array('type' => 'bool', 'default' => 1, 'description' => 'Notify by email of subscriptions'),
        'emailnotifyfav' => array('type' => 'bool', 'default' => 1, 'description' => 'Notify by email of favorites'),
        'emailnotifynudge' => array('type' => 'bool', 'default' => 1, 'description' => 'Notify by email of nudges'),
        'emailnotifymsg' => array('type' => 'bool', 'default' => 1, 'description' => 'Notify by email of direct messages'),
        'emailnotifyattn' => array('type' => 'bool', 'default' => 1, 'description' => 'Notify by email of @-replies'),
        'emailmicroid' => array('type' => 'bool', 'default' => 1, 'description' => 'whether to publish email microid'),
        'language' => array('type' => 'varchar', 'length' => 50, 'description' => 'preferred language'),
        'timezone' => array('type' => 'varchar', 'length' => 50, 'description' => 'timezone'),
        'emailpost' => array('type' => 'bool', 'default' => 1, 'description' => 'Post by email'),
        'sms' => array('type' => 'varchar', 'length' => 64, 'description' => 'sms phone number'),
        'carrier' => array('type' => 'int', 'description' => 'foreign key to sms_carrier'),
        'smsnotify' => array('type' => 'bool', 'default' => 0, 'description' => 'whether to send notices to SMS'),
        'smsreplies' => array('type' => 'bool', 'default' => 0, 'description' => 'whether to send notices to SMS on replies'),
        'smsemail' => array('type' => 'varchar', 'length' => 255, 'description' => 'built from sms and carrier'),
        'uri' => array('type' => 'varchar', 'length' => 255, 'description' => 'universally unique identifier, usually a tag URI'),
        'autosubscribe' => array('type' => 'bool', 'default' => 0, 'description' => 'automatically subscribe to users who subscribe to us'),
        'urlshorteningservice' => array('type' => 'varchar', 'length' => 50, default 'ur1.ca' 'description' => 'service to use for auto-shortening URLs'),
        'inboxed' => array('type' => 'bool', 'default' => 0, 'description' => 'has an inbox been created for this user?'),
        'design_id' => array('type' => 'int', 'description' => 'id of a design'),
        'viewdesigns' => array('type' => 'bool', 'default' => 1, 'description' => 'whether to view user-provided designs'),

        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
    ),
    'primary key' => array('id'),
    'unique keys' => array(
        'user_name_idx' => array('name'),
        'user_email_idx' => array('email'),
        'user_incomingemail_idx' => array('incomingemail'),
        'user_sms_idx' => array('sms'),
        'user_uri_idx' => array('uri'),
    ),
    'foreign keys' => array(
        'id' => array('profile' => 'id'),
        'carrier' => array('sms_carrier' => 'id'),
        'design_id' => array('desgin' => 'id'),
    ),
    'indexes' => array(
        'user_smsemail_idx' => array('smsemail'),
    ),
);

$schema['remote_profile'] = array(
    'description' => 'remote people (OMB)',
    'fields' => array(
        'id' => array('type' => 'int', 'description' => 'foreign key to profile table'),
        'uri' => array('type' => 'varchar', 'length' => 255, 'description' => 'universally unique identifier, usually a tag URI'),
        'postnoticeurl' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL we use for posting notices'),
        'updateprofileurl' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL we use for updates to this profile'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
    ),
    'primary key' => array('id'),
    'unique keys' => array(
        'remote_profile_uri_idx' => array('uri'),
    ),
    'foreign keys' => array(
        'id' => array('profile' => 'id'),
    ),
);

$schema['subscription'] = array(
    'fields' => array(
        'subscriber' => array('type' => 'int', 'not null' => true, 'description' => 'profile listening'),
        'subscribed' => array('type' => 'int', 'not null' => true, 'description' => 'profile being listened to'),
        'jabber' => array('type' => 'bool', 'default' => 1, 'description' => 'deliver jabber messages'),
        'sms' => array('type' => 'bool', 'default' => 1, 'description' => 'deliver sms messages'),
        'token' => array('type' => 'varchar', 'length' => 255, 'description' => 'authorization token'),
        'secret' => array('type' => 'varchar', 'length' => 255, 'description' => 'token secret'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
    ),
    'primary key' => array('subscriber', 'subscribed'),
    'indexes' => array(
        'subscription_subscriber_idx' => array('subscriber', 'created'),
        'subscription_subscribed_idx' => array('subscribed', 'created'),
        'subscription_token_idx' => array('token'),
    ),
);

$schema['notice'] = array(
    'fields' => array(
        'id' => array('type' => 'serial', 'description' => 'unique identifier'),
        'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'who made the update'),
        'uri' => array('type' => 'varchar', 'length' => 255, 'description' => 'universally unique identifier, usually a tag URI'),
        'content' => array('type' => 'text', 'description' => 'update content'),
        'rendered' => array('type' => 'text', 'description' => 'HTML version of the content'),
        'url' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL of any attachment (image, video, bookmark, whatever)'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
        'reply_to' => array('type' => 'int', 'description' => 'notice replied to (usually a guess)'),
        'is_local' => array('type' => 'bool', 'default' => 0, 'description' => 'notice was generated by a user'),
        'source' => array('type' => 'varchar', 'length' => 32, 'description' => 'source of comment, like "web", "im", or "clientname"'),
        'conversation' => array('type' => 'int', 'description' => 'id of root notice in this conversation'),
        'lat' => array('type' => 'numeric', 'precision' => 10, 'scale' => 7, 'description' => 'latitude'),
        'lon' => array('type' => 'numeric', 'precision' => 10, 'scale' => 7, 'description' => 'longitude'),
        'location_id' => array('type' => 'int', 'description' => 'location id if possible'),
        'location_ns' => array('type' => 'int', 'description' => 'namespace for location'),
        'repeat_of' => array('type' => 'int', 'description' => 'notice this is a repeat of'),
    ),
    'primary key' => array('id'),
    'unique keys' => array(
        'notice_uri_idx' => array('uri'),
    ),
    'foreign keys' => array(
        'profile_id' => array('profile' => 'id'),
        'reply_to' => array('notice', 'id'),
        'conversation' => array('conversation', 'id'), # note... used to refer to notice.id
        'repeat_of' => array('notice', 'id'), # @fixme: what about repeats of deleted notices?
    ),
    'indexes' => array(
        'notice_profile_id_idx' => array('profile_id,created,id'),
        'notice_conversation_idx' => array('conversation'),
        'notice_created_idx' => array('created'),
        'notice_replyto_idx' => array('reply_to'),
        'notice_repeatof_idx' => array('repeat_of'),
        'FULLTEXT' => array('content'),
    ),
);

$schema['notice_source'] = array(
    'fields' => array(
        'code' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'source code'),
        'name' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'name of the source'),
        'url' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'url to link to'),
        'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
    ),
    'primary key' => array('code'),
);

$schema['reply'] = array(
    'fields' => array(
        'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice that is the reply'),
        'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'profile replied to'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
        'replied_id' => array('type' => 'int', 'description' => 'notice replied to (not used, see notice.reply_to)'),
    ),
    'primary key' => array('notice_id', 'profile_id'),
    'foreign keys' => array(
        'notice_id' => array('notice' => 'id'),
        'profile_id' => array('profile' => 'id'),
    ),
    'indexes' => array(
        'reply_notice_id_idx' => array('notice_id'),
        'reply_profile_id_idx' => array('profile_id'),
        'reply_replied_id_idx' => array('replied_id'),
    ),
);

$schema['fave'] = array(
    'fields' => array(
        'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice that is the favorite'),
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user who likes this notice'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('notice_id', 'user_id'),
    'foreign keys' => array(
        'notice_id' => array('notice', 'id'),
        'user_id' => array('profile', 'id'), // note: formerly referenced notice.id, but we can now record remote users' favorites
    ),
    'indexes' => array(
        'fave_notice_id_idx' => array('notice_id'),
        'fave_user_id_idx' => array('user_id', 'modified'),
        'fave_modified_idx' => array('modified'),
    ),
);

/* tables for OAuth */

$schema['consumer'] = array(
    'description' => 'OAuth consumer record',
    'fields' => array(
        'consumer_key' => array('type' => 'varchar', 'length' => 255, 'description' => 'unique identifier, root URL'),
        'consumer_secret' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'secret value'),
        'seed' => array('type' => 'char', 'length' => 32, 'not null' => true, 'description' => 'seed for new tokens by this consumer'),

        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
    ),
    'primary key' => array('consumer_key'),
);

$schema['token'] = array(
    'description' => 'OAuth token record',
    'fields' => array(
        'consumer_key' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'unique identifier, root URL'),
        'tok' => array('type' => 'char', 'length' => 32, 'not null' => true, 'description' => 'identifying value'),
        'secret' => array('type' => 'char', 'length' => 32, 'not null' => true, 'description' => 'secret value'),
        'type' => array('type' => 'bool', 'not null' => true, 'default' => 0, 'description' => 'request or access'),
        'state' => array('type' => 'bool', 'default' => 0, 'description' => 'for requests, 0 = initial, 1 = authorized, 2 = used'),
        'verifier' => array('type' => 'varchar', 'length' => 255, 'description' => 'verifier string for OAuth 1.0a'),
        'verified_callback' => array('type' => 'varchar', 'length' => 255, 'description' => 'verified callback URL for OAuth 1.0a'),

        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
    ),
    'primary key' => array('consumer_key', 'tok'),
    'foreign keys' => array(
        'consumer_key' => array('consumer' => 'consumer_key'),
    ),
);

$schema['nonce'] = array(
    'description' => 'OAuth nonce record',
    'fields' => array(
        'consumer_key' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'unique identifier, root URL'),
        'tok' => array('type' => 'char', 'length' => 32, 'description' => 'buggy old value, ignored'),
        'nonce' => array('type' => 'char', 'length' => 32, 'not null' => true, 'description' => 'nonce'),
        'ts' => array('type' => 'datetime', 'not null' => true, 'description' => 'timestamp sent'),

        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
    ),
    'primary key' => array('consumer_key', 'ts', 'nonce'),
);

$schema['oauth_application'] = array(
    'description' => 'OAuth application registration record',
    'fields' => array(
        'id' => array('type' => 'serial', 'description' => 'unique identifier'),
        'owner' => array('type' => 'int', 'not null' => true, 'description' => 'owner of the application'),
        'consumer_key' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'application consumer key',
        'name' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'name of the application'),
        'description' => array('type' => 'varchar', 'length' => 255, 'description' => 'description of the application'),
        'icon' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'application icon'),
        'source_url' => array('type' => 'varchar', 'length' => 255, 'description' => 'application homepage - used for source link'),
        'organization' => array('type' => 'varchar', 'length' => 255, 'description' => 'name of the organization running the application'),
        'homepage' => array('type' => 'varchar', 'length' => 255, 'description' => 'homepage for the organization'),
        'callback_url' => array('type' => 'varchar', 'length' => 255, 'description' => 'url to redirect to after authentication'),
        'type' => array('type' => 'bool', 'default' => 0, 'description' => 'type of app, 1 = browser, 2 = desktop'),
        'access_type' => array('type' => 'bool', 'default' => 0, 'description' => 'default access type, bit 1 = read, bit 2 = write'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', comment 'date this record was modified'),
    ),
    'primary key' => array('id'),
    'unique keys' => array(
        'oauth_application_name_idx' => array('name'), // in the long run, we should perhaps not force these unique, and use another source id
    ),
    'foreign keys' => array(
        'owner' => array('profile' => 'id'), // Are remote users allowed to create oauth application records?
        'consumer_key' => array('consumer' => 'consumer_key'),
    ),
);

$schema['oauth_application_user'] = array(
    'fields' => array(
        'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'user of the application'),
        'application_id' => array('type' => 'int', 'not null' => true, 'description' => 'id of the application'),
        'access_type' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'access type, bit 1 = read, bit 2 = write'),
        'token' => array('type' => 'varchar', 'length' => 255, 'description' => 'request or access token'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
    ),
    'primary key' => array('profile_id', 'application_id'),
    'foreign keys' => array(
        'profile_id' => array('profile' => 'id'),
        'application_id' => array('oauth_application' => 'id'),
    ),
);

/* These are used by JanRain OpenID library */

$schema['oid_associations'] = array(
    'fields' => array(
        'server_url' => array('type' => 'blob'),
        'handle' => array('type' => 'varchar', 'length' => 255, character set latin1,
        'secret' => array('type' => 'blob'),
        'issued' => array('type' => 'int'),
        'lifetime' => array('type' => 'int'),
        'assoc_type' => array('type' => 'varchar', 'length' => 64),
    ),
    'primary key' => array(array('server_url', 255), 'handle'),
);

$schema['oid_nonces'] = array(
    'fields' => array(
        'server_url' => array('type' => 'varchar', 'length' => 2047),
        'timestamp' => array('type' => 'int'),
        'salt' => array('type' => 'char', 'length' => 40),
    ),
    'unique keys' => array(
        'oid_nonces_server_url_type_idx' => array('server_url', 255), 'timestamp', 'salt'),
    ),
);

$schema['confirm_address'] = array(
    'fields' => array(
        'code' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'good random code'),
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user who requested confirmation' references user (id),
        'address' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'address (email, xmpp, SMS, etc.)'),
        'address_extra' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'carrier ID, for SMS'),
        'address_type' => array('type' => 'varchar', 'length' => 8, 'not null' => true, 'description' => 'address type ("email", "xmpp", "sms")'),
        'claimed' => array('type' => 'datetime', 'description' => 'date this was claimed for queueing'),
        'sent' => array('type' => 'datetime', 'description' => 'date this was sent for queueing'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
    ),
    'primary key' => array('code'),
);

$schema['remember_me'] = array(
    'fields' => array(
        'code' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'good random code'),
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user who is logged in' references user (id),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified',
    ),
    'primary key' => array('code'),
);

$schema['queue_item'] = array(
    'fields' => array(
        'id' => array('type' => 'serial', 'description' => 'unique identifier'),
        'frame' => array('type' => 'blob', 'not null' => true, 'description' => 'data: object reference or opaque string'),
        'transport' => array('type' => 'varchar', 'length' => 8, 'not null' => true, 'description' => 'queue for what? "email", "xmpp", "sms", "irc", ...'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'claimed' => array('type' => 'datetime', 'description' => 'date this item was claimed'),
    ),
    'primary key' => array('id'),
    'indexes' => array(
        'queue_item_created_idx' => array('created'),
    ),
);

/* Hash tags */
$schema['notice_tag'] = array(
    'fields' => array(
        'tag' => array('type' => 'varchar', 'length' => 64, 'not null' => true, 'description' => 'hash tag associated with this notice'),
        'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice tagged' references notice (id),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
    ),
    'primary key' => array('tag', 'notice_id'),
    'indexes' => array(
        'notice_tag_created_idx' => array('created'),
        'notice_tag_notice_id_idx' => array('notice_id'),
    ),
);

/* Synching with foreign services */

$schema['foreign_service'] = array(
    'fields' => array(
        'id' => array('type' => 'int', 'not null' => true, 'description' => 'numeric key for service'),
        'name' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'name of the service'),
        'description' => array('type' => 'varchar', 'length' => 255, 'description' => 'description'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified',
    ),
    'primary key' => array('id'),
    'unique keys' => array(
        'foreign_service_name_idx' => 'name',
    ),
);

$schema['foreign_user'] = array(
    'fields' => array(
        'id' => array('type' => 'bigint', 'not null' => true, 'description' => 'unique numeric key on foreign service'),
        'service' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to service' references foreign_service(id),
        'uri' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'identifying URI'),
        'nickname' => array('type' => 'varchar', 'length' => 255, 'description' => 'nickname on foreign service'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
    ),
    'primary key' => array('id', 'service'),
    'unique keys' => array(
        'foreign_user_uri_idx' => 'uri',
    ),
);

$schema['foreign_link'] = array(
    'fields' => array(
        'user_id' => array('type' => 'int', 'description' => 'link to user on this system, if exists' references user (id),
        'foreign_id' => array('type' => 'int', 'size' => 'big', 'unsigned' => true, 'description' => 'link to user on foreign service, if exists' references foreign_user(id),
        'service' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to service' references foreign_service(id),
        'credentials' => array('type' => 'varchar', 'length' => 255, 'description' => 'authc credentials, typically a password'),
        'noticesync' => array('type' => 'int', 'size' => 'tiny', 'not null' => true, 'default' => 1, 'description' => 'notice synchronization, bit 1 = sync outgoing, bit 2 = sync incoming, bit 3 = filter local replies'),
        'friendsync' => array('type' => 'int', 'size' => 'tiny', 'not null' => true, 'default' => 2 'description' => 'friend synchronization, bit 1 = sync outgoing, bit 2 = sync incoming'),
        'profilesync' => array('type' => 'int', 'size' => 'tiny', 'not null' => true, 'default' => 1, 'description' => 'profile synchronization, bit 1 = sync outgoing, bit 2 = sync incoming'),
        'last_noticesync' => array('type' => 'datetime', 'description' => 'last time notices were imported'),
        'last_friendsync' => array('type' => 'datetime', 'description' => 'last time friends were imported'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
    ),
    'primary key' => array('user_id', 'foreign_id', 'service'),
    'indexes' => array(
        'foreign_user_user_id_idx' => array('user_id'),
    ),
);

$schema['foreign_subscription'] = array(
    'fields' => array(
        'service', 'type' => 'int', 'not null' => true, 'description' => 'service where relationship happens' references foreign_service(id),
        'subscriber', 'type' => 'int', 'not null' => true, 'description' => 'subscriber on foreign service' references foreign_user (id),
        'subscribed', 'type' => 'int', 'not null' => true, 'description' => 'subscribed user' references foreign_user (id),
        'created', 'type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
    ),
    'primary key' => array('service', 'subscriber', 'subscribed'),
    'indexes' => array(
        'foreign_subscription_subscriber_idx' => array('subscriber'),
        'foreign_subscription_subscribed_idx' => array('subscribed'),
    ),
);

$schema['invitation'] = array(
    'fields' => array(
        'code', 'type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'random code for an invitation'),
        'user_id', 'type' => 'int', 'not null' => true, 'description' => 'who sent the invitation' references user (id),
        'address', 'type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'invitation sent to'),
        'address_type', 'type' => 'varchar', 'length' => 8, 'not null' => true, 'description' => 'address type ("email", "xmpp", "sms")'),
        'created', 'type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
    ),
    'primary key' => array('code'),
    'indexes' => array(
        'invitation_address_idx' => ('address', 'address_type'),
        'invitation_user_id_idx' => ('user_id'),
    ),
);

$schema['message'] = array(
    'fields' => array(
        'id' => array('type' => 'serial', 'description' => 'unique identifier'),
        'uri' => array('type' => 'varchar', 'length' => 255, 'description' => 'universally unique identifier'),
        'from_profile' => array('type' => 'int', 'not null' => true, 'description' => 'who the message is from' references profile (id),
        'to_profile' => array('type' => 'int', 'not null' => true, 'description' => 'who the message is to' references profile (id),
        'content' => array('type' => 'text', 'description' => 'message content'),
        'rendered' => array('type' => 'text', 'description' => 'HTML version of the content'),
        'url' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL of any attachment (image, video, bookmark, whatever)'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
        'source' => array('type' => 'varchar', 'length' => 32, 'description' => 'source of comment, like "web", "im", or "clientname"'),

    'primary key' => array('id'),
    'unique key' => array(
        'message_uri_idx' => array('uri'),
    ),
    'indexes' => array(
        // @fixme these are really terrible indexes, since you can only sort on one of them at a time.
        // looks like we really need a (to_profile, created) for inbox and a (from_profile, created) for outbox
        'message_from_idx' => array('from_profile'),
        'message_to_idx' => array('to_profile'),
        'message_created_idx' => array('created'),
    ),
);

$schema['notice_inbox'] = array(
    'fields' => array(
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user receiving the message' references user (id),
        'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice received' references notice (id),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date the notice was created'),
        'source' => array('type' => 'bool', 'default' => 1, 'description' => 'reason it is in the inbox, 1=subscription'),
    ),
    'primary key' => array('user_id', 'notice_id'),
    'indexes' => array(
        'notice_inbox_notice_id_idx' => ('notice_id'),
    ),
);

$schema['profile_tag'] = array(
    'fields' => array(
        'tagger' => array('type' => 'int', 'not null' => true, 'description' => 'user making the tag' references user (id),
        'tagged' => array('type' => 'int', 'not null' => true, 'description' => 'profile tagged' references profile (id),
        'tag' => array('type' => 'varchar', 'length' => 64, 'not null' => true, 'description' => 'hash tag associated with this notice'),
        'modified' => array('type' => 'timestamp', 'description' => 'date the tag was added'),
    ),
    'primary key' => array('tagger', 'tagged', 'tag'),
    'indexes' => array(
        'profile_tag_modified_idx' => array('modified'),
        'profile_tag_tagger_tag_idx' => array('tagger', 'tag'),
        'profile_tag_tagged_idx' => array('tagged'),
    ),
);

$schema['profile_block'] = array(
    'fields' => array(
        'blocker' => array('type' => 'int', 'not null' => true, 'description' => 'user making the block' references user (id),
        'blocked' => array('type' => 'int', 'not null' => true, 'description' => 'profile that is blocked' references profile (id),
        'modified' => array('type' => 'timestamp', 'description' => 'date of blocking'),
    ),
    'primary key' => array('blocker', 'blocked'),
);

$schema['user_group'] = array(
    'fields' => array(
        'id' => array('type' => 'serial', 'description' => 'unique identifier'),

        'nickname' => array('type' => 'varchar', 'length' => 64, 'description' => 'nickname for addressing'),
        'fullname' => array('type' => 'varchar', 'length' => 255, 'description' => 'display name'),
        'homepage' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL, cached so we dont regenerate'),
        'description' => array('type' => 'text', 'description' => 'group description'),
        'location' => array('type' => 'varchar', 'length' => 255, 'description' => 'related physical location, if any'),

        'original_logo' => array('type' => 'varchar', 'length' => 255, 'description' => 'original size logo'),
        'homepage_logo' => array('type' => 'varchar', 'length' => 255, 'description' => 'homepage (profile) size logo'),
        'stream_logo' => array('type' => 'varchar', 'length' => 255, 'description' => 'stream-sized logo'),
        'mini_logo' => array('type' => 'varchar', 'length' => 255, 'description' => 'mini logo'),
        'design_id' => array('type' => 'int', 'description' => 'id of a design' references design(id),

        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),

        'uri' => array('type' => 'varchar', 'length' => 255, 'description' => 'universal identifier'),
        'mainpage' => array('type' => 'varchar', 'length' => 255, 'description' => 'page for group info to link to'),
    ),
    'primary key' => array('id'),
    'unique key' => array(
        'user_uri_idx' => array('uri'),
    ),
    'indexes' => array(
        'user_group_nickname_idx' => array('nickname'),
    ),
);

$schema['group_member'] = array(
    'fields' => array(
        'group_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to user_group' references user_group (id),
        'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to profile table' references profile (id),
        'is_admin' => array('type' => 'boolean', 'default' => false, 'description' => 'is this user an admin?'),

        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
    ),
    'primary key' => array('group_id', 'profile_id'),
    'indexes' => array(
        // @fixme probably we want a (profile_id, created) index here?
        'group_member_profile_id_idx' => array('profile_id'),
        'group_member_created_idx' => array('created'),
    ),
);

$schema['related_group'] = array(
    'fields' => array(
        'group_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to user_group' references user_group (id),
        'related_group_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to user_group' references user_group (id),

        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
    ),
    'primary key' => array('group_id', 'related_group_id'),
);

$schema['group_inbox'] = array(
    'fields' => array(
        'group_id' => array('type' => 'int', 'not null' => true, 'description' => 'group receiving the message' references user_group (id),
        'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice received' references notice (id),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date the notice was created'),
    ),
    'primary key' => array('group_id', 'notice_id'),
    'indexes' => array(
        'group_inbox_created_idx' => array('created'),
        'group_inbox_notice_id_idx' => array('notice_id'),
    ),
);

$schema['file'] = array(
    'fields' => array(
        'id' => array('type' => 'serial'),
        'url' => array('type' => 'varchar', 'length' => 255, 'description' => 'destination URL after following redirections'),
        'mimetype' => array('type' => 'varchar', 'length' => 50, 'description' => 'mime type of resource'),
        'size' => array('type' => 'int', 'description' => 'size of resource when available'),
        'title' => array('type' => 'varchar', 'length' => 255, 'description' => 'title of resource when available'),
        'date' => array('type' => 'int', 'description' => 'date of resource according to http query'),
        'protected' => array('type' => 'int', 'description' => 'true when URL is private (needs login)'),
        'filename' => array('type' => 'varchar', 'length' => 255, 'description' => 'if a local file, name of the file'),

        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
    ),
    'primary key' => array('id'),
    'unique keys' => array(
        'file_url_idx' => array('url'),
    ),
);

$schema['file_oembed'] = array(
    'fields' => array(
        'file_id' => array('type' => 'int', 'description' => 'oEmbed for that URL/file' references file (id),
        'version' => array('type' => 'varchar', 'length' => 20, 'description' => 'oEmbed spec. version'),
        'type' => array('type' => 'varchar', 'length' => 20, 'description' => 'oEmbed type: photo, video, link, rich'),
        'mimetype' => array('type' => 'varchar', 'length' => 50, 'description' => 'mime type of resource'),
        'provider' => array('type' => 'varchar', 'length' => 50, 'description' => 'name of this oEmbed provider'),
        'provider_url' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL of this oEmbed provider'),
        'width' => array('type' => 'int', 'description' => 'width of oEmbed resource when available'),
        'height' => array('type' => 'int', 'description' => 'height of oEmbed resource when available'),
        'html' => array('type' => 'text', 'description' => 'html representation of this oEmbed resource when applicable'),
        'title' => array('type' => 'varchar', 'length' => 255, 'description' => 'title of oEmbed resource when available'),
        'author_name' => array('type' => 'varchar', 'length' => 50, 'description' => 'author name for this oEmbed resource'),
        'author_url' => array('type' => 'varchar', 'length' => 255, 'description' => 'author URL for this oEmbed resource'),
        'url' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL for this oEmbed resource when applicable (photo, link)'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'
    ),
    'primary key' => array('file_id'),
);

$schema['file_redirection'] = array(
    'fields' => array(
        'url' => array('type' => 'varchar', 'length' => 255, 'description' => 'short URL (or any other kind of redirect) for file (id)'),
        'file_id' => array('type' => 'int', 'description' => 'short URL for what URL/file' references file (id),
        'redirections' => array('type' => 'int', 'description' => 'redirect count'),
        'httpcode' => array('type' => 'int', 'description' => 'HTTP status code (20x, 30x, etc.)'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'
    ),
    'primary key' => array('url'),
);

$schema['file_thumbnail'] = array(
    'fields' => array(
        'file_id' => array('type' => 'int',  'description' => 'thumbnail for what URL/file' references file (id),
        'url' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL of thumbnail'),
        'width' => array('type' => 'int', 'description' => 'width of thumbnail'),
        'height' => array('type' => 'int', 'description' => 'height of thumbnail'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
    'primary key' => array('file_id'),
    'unique keys' => array(
        'file_thumbnail_url_idx' => array('url'),
    ),
);

$schema['file_to_post'] = array(
    'fields' => array(
        'file_id' => array('type' => 'int', 'description' => 'id of URL/file' references file (id),
        'post_id' => array('type' => 'int', 'description' => 'id of the notice it belongs to' references notice (id),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
    ),
    'primary key' => array('file_id', 'post_id'),
    'indexes' => array(
        'post_id_idx' => array('post_id'),
    ),
);

$schema['design'] = array(
    'fields' => array(
        'id' => array('type' => 'serial', 'description' => 'design ID'),
        'backgroundcolor' => array('type' => 'int', 'description' => 'main background color'),
        'contentcolor' => array('type' => 'int', 'description' => 'content area background color'),
        'sidebarcolor' => array('type' => 'int', 'description' => 'sidebar background color'),
        'textcolor' => array('type' => 'int', 'description' => 'text color'),
        'linkcolor' => array('type' => 'int', 'description' => 'link color'),
        'backgroundimage' => array('type' => 'varchar', 'length' => 255, 'description' => 'background image, if any'),
        'disposition' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'bit 1 = hide background image, bit 2 = display background image, bit 4 = tile background image'
    ),
    'primary key' => array('id'),
);

$schema['group_block'] = array(
    'fields' => array(
        'group_id' => array('type' => 'int', 'not null' => true, 'description' => 'group profile is blocked from' references user_group (id),
        'blocked' => array('type' => 'int', 'not null' => true, 'description' => 'profile that is blocked' references profile (id),
        'blocker' => array('type' => 'int', 'not null' => true, 'description' => 'user making the block' references user (id),
        'modified' => array('type' => 'timestamp', 'description' => 'date of blocking'),
    ),
    'primary key' => array('group_id', 'blocked'),
);

$schema['group_alias'] = array(
    'fields' => array(
        'alias' => array('type' => 'varchar', 'length' => 64, 'description' => 'additional nickname for the group'),
        'group_id' => array('type' => 'int 'not null' => true, 'description' => 'group profile is blocked from' references user_group (id),
        'modified' => array('type' => 'timestamp 'description' => 'date alias was created'),
    'primary key' => array('alias'),
    'indexes' => array(
        'group_alias_group_id_idx' => array('group_id'),
    ),
);

$schema['session'] = array(
    'fields' => array(
        'id' => array('type' => 'varchar', 'length' => 32, primary key 'description' => 'session ID'),
        'session_data' => array('type' => 'text', 'description' => 'session data'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),

    'indexes' => array(
    index session_modified_idx (modified)

);

$schema['deleted_notice'] = array(
    'fields' => array(
        'id' => array('type' => 'int', primary key 'description' => 'identity of notice'),
        'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'author of the notice'),
        'uri' => array('type' => 'varchar', 'length' => 255, unique key 'description' => 'universally unique identifier, usually a tag URI'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date the notice record was created'),
        'deleted' => array('type' => 'datetime', 'not null' => true, 'description' => 'date the notice record was created'),

    'indexes' => array(
    index deleted_notice_profile_id_idx (profile_id)

);

$schema['config'] = array(
    'fields' => array(
        'section' => array('type' => 'varchar', 'length' => 32, 'description' => 'configuration section'),
        'setting' => array('type' => 'varchar', 'length' => 32, 'description' => 'configuration setting'),
        'value' => array('type' => 'varchar', 'length' => 255, 'description' => 'configuration value'),

    'primary key' => array(section, setting)

);

$schema['profile_role'] = array(
    'fields' => array(
        'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'account having the role' references profile (id),
        'role   ', 'type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'string representing the role'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date the role was granted'),

    'primary key' => array(profile_id, role)

);

$schema['location_namespace'] = array(
    'fields' => array(
        'id' => array('type' => 'int', primary key 'description' => 'identity for this namespace'),
        'description' => array('type' => 'varchar', 'length' => 255, 'description' => 'description of the namespace'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date the record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'

);

$schema['login_token'] = array(
    'fields' => array(
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user owning this token' references user (id),
        'token' => array('type' => 'char', 'length' => 32, 'not null' => true, 'description' => 'token useable for logging in'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),

    'primary key' => array(user_id)
);

$schema['user_location_prefs'] = array(
    'fields' => array(
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user who has the preference' references user (id),
        'share_location' => array('type' => 'bool', 'default' => 1, 'description' => 'Whether to share location data'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),
    ),
    'primary key' => array(user_id)
);

$schema['inbox'] = array(
    'fields' => array(
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user receiving the notice' references user (id),
        'notice_ids' => array('type' => 'blob', 'description' => 'packed list of notice ids'),
    ),
    'primary key' => array(user_id)

);

// @fixme possibly swap this for a more general prefs table?
$schema['user_im_prefs'] = array(
    'fields' => array(
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user',
        'screenname' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'screenname on this service'),
        'transport' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'transport (ex xmpp, aim)'),
        'notify' => array('type' => 'bool', 'not null' => true, 'default' => 0, 'description' => 'Notify when a new notice is sent'),
        'replies' => array('type' => 'bool', 'not null' => true, 'default' => 0, 'description' => 'Send replies  from people not subscribed to'),
        'microid' => array('type' => 'bool', 'not null' => true, 'default' => 1, 'description' => 'Publish a MicroID'),
        'updatefrompresence' => array('type' => 'bool', 'not null' => true, 'default' => 0, 'description' => 'Send replies  from people not subscribed to.'),
        'created' => array('type' => 'timestamp', 'not null' => true, DEFAULT CURRENT_TIMESTAMP 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),

    'primary key' => array('user_id', 'transport'),
    'unique keys' => array(
        'transport_screenname_key' => array('transport', 'screenname'),
    ),
    'foreign keys' => array(
        'user_id' => array('user' => 'id'),
    ),
);

$schema['conversation'] = array(
    'fields' => array(
        'id' => array('type' => 'serial', 'description' => 'unique identifier'),
        'uri' => array('type' => 'varchar', 'length' => 225, 'description' => 'URI of the conversation'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'
    ),
    'primary key' => array('id'),
    'unique keys' => array(
        'conversation_uri_idx' => array('uri'),
    ),
);

$schema['local_group'] = array(
    'description' => 'Record for a user group on the local site, with some additional info not in user_group',
    'fields' => array(
        'group_id' => array('type' => 'int', 'description' => 'group represented'),
        'nickname' => array('type' => 'varchar', 'length' => 64, 'description' => 'group represented'),

        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'
    ),
    'primary key' => array('group_id'),
    'foreign keys' => array(
        'group_id' => array('user_group' => 'id'),
    ),
    'unique keys' => array(
        'local_group_nickname_idx' => array('nickname'),
    ),
);

$schema['user_urlshortener_prefs'] = array(
    'fields' => array(
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user'),
        'urlshorteningservice' => array('type' => 'varchar', 'length' => 50, 'default' => 'ur1.ca', 'description' => 'service to use for auto-shortening URLs'),
        'maxurllength' => array('type' => 'int', 'not null' => true, 'description' => 'urls greater than this length will be shortened, 0 = always, null = never'),
        'maxnoticelength' => array('type' => 'int', 'not null' => true, 'description' => 'notices with content greater than this value will have all urls shortened, 0 = always, null = never'),
        
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'description' => 'date this record was modified'),

   'primary key' => array('user_id'),
   'foreign keys' => array(
        'user_id' => array('user' => 'id'),
   ),
);

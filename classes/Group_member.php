<?php
/**
 * Table Definition for group_member
 */

class Group_member extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'group_member';                    // table name
    public $group_id;                        // int(4)  primary_key not_null
    public $profile_id;                      // int(4)  primary_key not_null
    public $is_admin;                        // tinyint(1)
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Group_member',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Group_member', $kv);
    }

    static function join($group_id, $profile_id)
    {
        $member = new Group_member();

        $member->group_id   = $group_id;
        $member->profile_id = $profile_id;
        $member->created    = common_sql_now();

        $result = $member->insert();

        if (!$result) {
            common_log_db_error($member, 'INSERT', __FILE__);
            // TRANS: Exception thrown when joining a group fails.
            throw new Exception(_("Group join failed."));
        }

        return true;
    }

    static function leave($group_id, $profile_id)
    {
        $member = Group_member::pkeyGet(array('group_id' => $group_id,
                                              'profile_id' => $profile_id));

        if (empty($member)) {
            // TRANS: Exception thrown when trying to leave a group the user is not a member of.
            throw new Exception(_("Not part of group."));
        }

        $result = $member->delete();

        if (!$result) {
            common_log_db_error($member, 'INSERT', __FILE__);
            // TRANS: Exception thrown when trying to leave a group fails.
            throw new Exception(_("Group leave failed."));
        }

        return true;
    }

    function getMember()
    {
        $member = Profile::staticGet('id', $this->profile_id);

        if (empty($member)) {
            throw new Exception("Profile ID {$this->profile_id} invalid.");
        }

        return $member;
    }

    function getGroup()
    {
        $group  = User_group::staticGet('id', $this->group_id);

        if (empty($group)) {
            throw new Exception("Group ID {$this->group_id} invalid.");
        }

        return $group;
    }

    function asActivity()
    {
        $member = $this->getMember();
        $group  = $this->getGroup();

        $act = new Activity();

        $act->id = TagURI::mint('join:%d:%d:%s',
                                $member->id,
                                $group->id,
                                common_date_iso8601($this->created));

        $act->actor     = ActivityObject::fromProfile($member);
        $act->verb      = ActivityVerb::JOIN;
        $act->objects[] = ActivityObject::fromGroup($group);

        $act->time  = strtotime($this->created);
        $act->title = _("Join");

        // TRANS: Success message for subscribe to group attempt through OStatus.
        // TRANS: %1$s is the member name, %2$s is the subscribed group's name.
        $act->content = sprintf(_('%1$s has joined group %2$s.'),
                                $member->getBestName(),
                                $group->getBestName());

        return $act;
    }
}

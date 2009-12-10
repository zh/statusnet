<?php

class InboxNoticeList extends NoticeList
{
    var $owner = null;

    function __construct($notice, $owner, $out=null)
    {
        parent::__construct($notice, $out);
        $this->owner  = $owner;
    }

    function newListItem($notice)
    {
        return new InboxNoticeListItem($notice, $this->owner, $this->out);
    }
}

class InboxNoticeListItem extends NoticeListItem
{
    var $owner = null;
    var $ib    = null;

    function __construct($notice, $owner, $out=null)
    {
        parent::__construct($notice, $out);
        $this->owner = $owner;

        $this->ib = Notice_inbox::pkeyGet(array('user_id' => $owner->id,
                                                'notice_id' => $notice->id));
    }

    function showAuthor()
    {
        parent::showAuthor();
        if ($this->ib->source == NOTICE_INBOX_SOURCE_FORWARD) {
            $this->out->element('span', 'forward', _('Fwd'));
        }
    }

    function showEnd()
    {
        if ($this->ib->source == NOTICE_INBOX_SOURCE_FORWARD) {

            $forward = new Forward();

            // FIXME: scary join!

            $forward->query('SELECT profile_id '.
                            'FROM forward JOIN subscription ON forward.profile_id = subscription.subscribed '.
                            'WHERE subscription.subscriber = ' . $this->owner->id . ' '.
                            'AND forward.notice_id = ' . $this->notice->id . ' '.
                            'ORDER BY forward.created ');

            $n = 0;

            $firstForwarder = null;

            while ($forward->fetch()) {
                if (empty($firstForwarder)) {
                    $firstForwarder = Profile::staticGet('id', $forward->profile_id);
                }
                $n++;
            }

            $forward->free();
            unset($forward);

            $this->out->elementStart('span', 'forwards');

            $link = XMLStringer::estring('a', array('href' => $firstForwarder->profileurl),
                                         $firstForwarder->nickname);

            if ($n == 1) {
                $this->out->raw(sprintf(_('Forwarded by %s'), $link));
            } else {
                // XXX: use that cool ngettext thing
                $this->out->raw(sprintf(_('Forwarded by %s and %d other(s)'), $link, $n - 1));
            }

            $this->out->elementEnd('span');
        }
        parent::showEnd();
    }
}

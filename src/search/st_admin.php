<?php
// search/st_admin.php -- HotCRP helper class for searching for papers
// Copyright (c) 2006-2019 Eddie Kohler; see LICENSE.

class Admin_SearchTerm extends SearchTerm {
    private $users;
    private $flags;
    const ALLOW_NONE = 1;

    function __construct($match, $flags) {
        parent::__construct("admin");
        $this->match = $match;
        $this->flags = $flags;
    }
    static function parse($word, SearchWord $sword, PaperSearch $srch) {
        $flags = 0;
        if (($word === "any" || $word === "" || $word === "yes") && !$sword->quoted)
            $match = true;
        else if (($word === "none" || $word === "no") && !$sword->quoted)
            $match = false;
        else {
            $match = $srch->matching_contacts($word, $sword->quoted, true);
            foreach ($match as $u)
                if ($u->privChair)
                    $flags |= self::ALLOW_NONE;
        }
        return new Admin_SearchTerm($match, $flags);
    }
    function sqlexpr(SearchQueryInfo $sqi) {
        $sqi->add_column("managerContactId", "Paper.managerContactId");
        if ($sqi->conf->check_track_admin_sensitivity()
            || ($this->flags & self::ALLOW_NONE))
            return "true";
        else if ($this->match === true)
            return "Paper.managerContactId!=0";
        else if ($this->match === false)
            return "Paper.managerContactId=0";
        else {
            $cs = array_map(function ($p) { return $p->contactId; }, $this->match);
            return "(Paper.managerContactId" . CountMatcher::sqlexpr_using($cs) . ")";
        }
    }
    function exec(PaperInfo $row, PaperSearch $srch) {
        if (!$srch->user->can_view_manager($row))
            return $this->match === false || ($this->flags & self::ALLOW_NONE);
        else if (is_bool($this->match))
            return $this->match === ($row->managerContactId != 0);
        else {
            foreach ($this->match as $u)
                if ($u->is_primary_administrator($row))
                    return true;
            return false;
        }
    }
}

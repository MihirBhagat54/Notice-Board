<?php
// =============================================================
// app/helpers/NoticeHelper.php — Notice query & display helpers
// =============================================================

class NoticeHelper
{
    // Grades 1–12 constant
    public const GRADES = ['1','2','3','4','5','6','7','8','9','10','11','12'];

    // ── Fetch notices visible to a given user ─────────────────
    public static function getVisibleNotices(
        int    $userID,
        string $role,
        ?string $grade,          // student's own grade (null for Admin/Teacher)
        array  $filters = []
    ): array {
        $where  = ["n.deletedAt IS NULL", "n.active = 1"];
        $types  = '';
        $params = [];

        // Build scope gate
        // A notice is visible when ANY of:
        //   1. scope = General
        //   2. scope = Role Based  AND targetRole  = this user's role
        //   3. scope = Individual  AND targetUserID = this user's ID
        //   4. scope = Student Grade X AND this user is Student with grade X
        $gradeClause = '';
        if ($role === 'Student' && $grade !== null) {
            $gradeClause = "OR (s.scopeName = CONCAT('Student Grade ', ?) AND n.targetGrade = ?)";
            $types      .= 'ss';
            $params[]    = $grade;
            $params[]    = $grade;
        }

        $where[]  = "(
            s.scopeName = 'General'
            OR (s.scopeName = 'Role Based' AND n.targetRole = ?)
            OR (s.scopeName = 'Individual' AND n.targetUserID = ?)
            {$gradeClause}
        )";
        $types   .= 'si';
        $params[] = $role;
        $params[] = $userID;

        self::applyCommonFilters($filters, $where, $types, $params);

        return self::runNoticeQuery(implode(' AND ', $where), $types, $params);
    }

    // ── Fetch ALL notices (Admin view, no scope gate) ──────────
    public static function getAllNotices(array $filters = []): array
    {
        $where  = ['n.deletedAt IS NULL'];
        $types  = '';
        $params = [];

        self::applyCommonFilters($filters, $where, $types, $params);

        return self::runNoticeQuery(implode(' AND ', $where), $types, $params);
    }

    // ── Shared SQL runner ──────────────────────────────────────
    private static function runNoticeQuery(
        string $whereSQL,
        string $types,
        array  $params
    ): array {
        $sql = "SELECT n.*,
                       nc.categoryName, nc.subCategory,
                       ns.scopeName,
                       u.fullName  AS authorName,
                       u.role      AS authorRole
                FROM   notices n
                JOIN   notice_categories nc ON n.categoryID = nc.categoryID
                JOIN   notice_scopes     ns ON n.scopeID    = ns.scopeID
                JOIN   users             u  ON n.createdBy  = u.userID
                WHERE  {$whereSQL}
                ORDER  BY n.publishDate DESC";

        return Database::fetchAll($sql, $types, ...$params);
    }

    // ── Shared filter clauses ──────────────────────────────────
    private static function applyCommonFilters(
        array   $filters,
        array   &$where,
        string  &$types,
        array   &$params
    ): void {
        if (!empty($filters['categoryID'])) {
            $where[]  = 'n.categoryID = ?';
            $types   .= 'i';
            $params[] = (int)$filters['categoryID'];
        }
        if (!empty($filters['scopeID'])) {
            $where[]  = 'n.scopeID = ?';
            $types   .= 'i';
            $params[] = (int)$filters['scopeID'];
        }
        if (!empty($filters['search'])) {
            $where[]  = '(n.title LIKE ? OR n.description LIKE ?)';
            $types   .= 'ss';
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }
    }

    // ── Soft-delete ────────────────────────────────────────────
    public static function softDelete(int $noticeID, int $deletedBy): bool
    {
        return Database::query(
            'UPDATE notices SET deletedAt = NOW(), deletedBy = ?, active = 0 WHERE noticeID = ?',
            'ii', $deletedBy, $noticeID
        ) !== false;
    }

    // ── Lookup helpers ─────────────────────────────────────────
    public static function getCategories(): array
    {
        return Database::fetchAll(
            'SELECT * FROM notice_categories WHERE isActive = 1 ORDER BY categoryName, subCategory'
        );
    }

    public static function getCategoriesGrouped(): array
    {
        $grouped = [];
        foreach (self::getCategories() as $c) {
            $grouped[$c['categoryName']][] = $c;
        }
        return $grouped;
    }

    public static function getScopes(): array
    {
        return Database::fetchAll('SELECT * FROM notice_scopes WHERE isActive = 1 ORDER BY scopeID');
    }

    /**
     * Returns true when scopeName matches "Student Grade X" pattern.
     */
    public static function isGradeScope(string $scopeName): bool
    {
        return (bool)preg_match('/^Student Grade \d{1,2}$/', $scopeName);
    }

    // ── Display helpers ────────────────────────────────────────
    public static function categoryIcon(string $cat): string
    {
        return [
            'Academic'           => 'fa-graduation-cap',
            'Administrative'     => 'fa-building-columns',
            'Examination'        => 'fa-file-pen',
            'Events'             => 'fa-calendar-star',
            'Holidays'           => 'fa-umbrella-beach',
            'Urgent / Emergency' => 'fa-triangle-exclamation',
            'Co-Curricular'      => 'fa-trophy',
            'Discipline'         => 'fa-scale-balanced',
        ][$cat] ?? 'fa-bell';
    }

    public static function categoryColor(string $cat): string
    {
        return [
            'Academic'           => '#4f8ef7',
            'Administrative'     => '#7c6af7',
            'Examination'        => '#f7914f',
            'Events'             => '#4fc9f7',
            'Holidays'           => '#4ff796',
            'Urgent / Emergency' => '#f74f4f',
            'Co-Curricular'      => '#f7e24f',
            'Discipline'         => '#c94ff7',
        ][$cat] ?? '#aaaaaa';
    }

    public static function isExpired(?string $expiryDate): bool
    {
        return $expiryDate && strtotime($expiryDate) < time();
    }
}

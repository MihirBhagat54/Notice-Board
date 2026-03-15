<?php
// =============================================================
// app/helpers/NoticeHelper.php — Notice query & display helpers
// =============================================================

class NoticeHelper
{
    // ── Fetch notices visible to a given user ─────────────────
    public static function getVisibleNotices(int $userID, string $role, array $filters = []): array
    {
        $where  = ["n.deletedAt IS NULL", "n.active = 1"];
        $types  = '';
        $params = [];

        // Scope filter: show General + matching Role-Based + Individual
        $where[]  = "(s.scopeName = 'General'
                      OR (s.scopeName = 'Role Based' AND n.targetRole = ?)
                      OR (s.scopeName = 'Individual'  AND n.targetUserID = ?))";
        $types   .= 'si';
        $params[] = $role;
        $params[] = $userID;

        self::applyCommonFilters($filters, $where, $types, $params);

        return self::runNoticeQuery(implode(' AND ', $where), $types, $params);
    }

    // ── Fetch ALL notices (admin view, no scope gate) ─────────
    public static function getAllNotices(array $filters = []): array
    {
        $where  = ['n.deletedAt IS NULL'];
        $types  = '';
        $params = [];

        self::applyCommonFilters($filters, $where, $types, $params);

        return self::runNoticeQuery(implode(' AND ', $where), $types, $params);
    }

    // ── Shared SQL runner ─────────────────────────────────────
    private static function runNoticeQuery(string $whereSQL, string $types, array $params): array
    {
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

    // ── Apply shared filter clauses ───────────────────────────
    private static function applyCommonFilters(
        array $filters,
        array &$where,
        string &$types,
        array &$params
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

    // ── Soft-delete a notice ──────────────────────────────────
    public static function softDelete(int $noticeID, int $deletedBy): bool
    {
        $stmt = Database::query(
            'UPDATE notices SET deletedAt = NOW(), deletedBy = ?, active = 0 WHERE noticeID = ?',
            'ii', $deletedBy, $noticeID
        );
        return $stmt !== false;
    }

    // ── Category/scope lookup helpers ─────────────────────────
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
        return Database::fetchAll('SELECT * FROM notice_scopes WHERE isActive = 1');
    }

    // ── Display helpers ───────────────────────────────────────
    public static function categoryIcon(string $cat): string
    {
        $map = [
            'Academic'           => 'fa-graduation-cap',
            'Administrative'     => 'fa-building-columns',
            'Examination'        => 'fa-file-pen',
            'Events'             => 'fa-calendar-star',
            'Holidays'           => 'fa-umbrella-beach',
            'Urgent / Emergency' => 'fa-triangle-exclamation',
            'Co-Curricular'      => 'fa-trophy',
            'Discipline'         => 'fa-scale-balanced',
        ];
        return $map[$cat] ?? 'fa-bell';
    }

    public static function categoryColor(string $cat): string
    {
        $map = [
            'Academic'           => '#4f8ef7',
            'Administrative'     => '#7c6af7',
            'Examination'        => '#f7914f',
            'Events'             => '#4fc9f7',
            'Holidays'           => '#4ff796',
            'Urgent / Emergency' => '#f74f4f',
            'Co-Curricular'      => '#f7e24f',
            'Discipline'         => '#c94ff7',
        ];
        return $map[$cat] ?? '#aaaaaa';
    }

    public static function isExpired(?string $expiryDate): bool
    {
        return $expiryDate && strtotime($expiryDate) < time();
    }
}

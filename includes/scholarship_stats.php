<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function getScholarshipSummary(PDO $pdo): array
{
    $sql = "
        SELECT
            COUNT(*) AS total_scholarships,

            COALESCE(SUM(
                CASE WHEN status = 'active'
                THEN 1 ELSE 0 END
            ), 0) AS active_scholarships,

            COALESCE(SUM(
                CASE WHEN status = 'active'
                    AND scope = 'national'
                THEN 1 ELSE 0 END
            ), 0) AS national_scholarships,

            COALESCE(SUM(
                CASE WHEN status = 'active'
                    AND scope = 'state/local'
                THEN 1 ELSE 0 END
            ), 0) AS state_scholarships,

            COALESCE(SUM(
                CASE WHEN status = 'active'
                    AND application_deadline < CURDATE()
                    AND (rolling_deadline IS NULL
                         OR rolling_deadline = 0)
                THEN 1 ELSE 0 END
            ), 0) AS expired_deadlines

        FROM scholarships
    ";

    $stmt = $pdo->query($sql);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}



function getScholarshipsByState(PDO $pdo): array
{
    $sql = "
        SELECT
            st.name AS state_name,
            COUNT(s.scholarship_id) AS total

        FROM scholarships s

        INNER JOIN states st
            ON s.state_id = st.state_id

        WHERE s.status = 'active'
          AND s.scope = 'state/local'

        GROUP BY st.state_id, st.name

        ORDER BY total DESC, st.name ASC
    ";

    $stmt = $pdo->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



function getScholarshipsByTheme(PDO $pdo): array
{
    $sql = "
        SELECT
            nt.theme_name,
            COUNT(s.scholarship_id) AS total

        FROM scholarships s

        INNER JOIN national_themes nt
            ON s.national_theme_id = nt.national_theme_id

        WHERE s.status = 'active'
          AND s.scope = 'national'

        GROUP BY nt.national_theme_id, nt.theme_name

        ORDER BY total DESC, nt.theme_name ASC
    ";

    $stmt = $pdo->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



function getScholarshipsByScope(PDO $pdo): array
{
    $sql = "
        SELECT
            scope,
            COUNT(*) AS total

        FROM scholarships

        WHERE status = 'active'
          AND scope IN ('national', 'state/local')

        GROUP BY scope

        ORDER BY scope
    ";

    $stmt = $pdo->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



function getUpcomingDeadlines(PDO $pdo): array
{
    $sql = "
        SELECT
            scholarship_id,
            scholarship_name,
            application_deadline

        FROM scholarships

        WHERE status = 'active'
          AND application_deadline >= CURDATE()
          AND (rolling_deadline IS NULL
               OR rolling_deadline = 0)

        ORDER BY application_deadline ASC,
                 scholarship_id ASC

        LIMIT 5
    ";

    $stmt = $pdo->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

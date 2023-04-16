<?php

/*  Poweradmin, a friendly web-based admin tool for PowerDNS.
 *  See <https://www.poweradmin.org> for more details.
 *
 *  Copyright 2007-2010 Rejo Zenger <rejo@zenger.nl>
 *  Copyright 2010-2023 Poweradmin Development Team
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Poweradmin\Application\Query;

class RecordSearch extends BaseSearch
{
    /**
     * Search for Records
     *
     * @param array $parameters Array with parameters which configures function
     * @param string $permission_view User permitted to view 'all' or 'own' zones
     * @param string $sort_records_by Column to sort record results
     * @param bool $iface_search_group_records
     * @param int $iface_rowamount Items per page
     * @param int $page
     * @return array
     */
    public function search_records(array $parameters, string $permission_view, string $sort_records_by, bool $iface_search_group_records, int $iface_rowamount, int $page = 1): array
    {
        $foundRecords = array();

        list($reverse_search_string, $parameters, $search_string) = $this->buildSearchString($parameters);

        $originalSqlMode = $this->handleSqlMode();

        if ($parameters['records']) {
            $foundRecords = $this->fetchRecords($search_string, $parameters['reverse'], $reverse_search_string, $permission_view, $iface_search_group_records, $sort_records_by, $iface_rowamount, $page);
        }

        $this->restoreSqlMode($originalSqlMode);

        return $foundRecords;
    }

    /**
     * @param mixed $search_string
     * @param $reverse
     * @param mixed $reverse_search_string
     * @param string $permission_view
     * @param bool $iface_search_group_records
     * @param string $sort_records_by
     * @param int $iface_rowamount
     * @param array $foundRecords
     * @return array
     */
    public function fetchRecords(mixed $search_string, $reverse, mixed $reverse_search_string, string $permission_view, bool $iface_search_group_records, string $sort_records_by, int $iface_rowamount, int $page): array
    {
        $offset = ($page - 1) * $iface_rowamount;

        $recordsQuery = '
            SELECT
                records.id,
                records.domain_id,
                records.name,
                records.type,
                records.content,
                records.ttl,
                records.prio,
                z.id as zone_id,
                z.owner,
                u.id as user_id,
                u.fullname
            FROM
                records
            LEFT JOIN zones z on records.domain_id = z.domain_id
            LEFT JOIN users u on z.owner = u.id
            WHERE
                (records.name LIKE ' . $this->db->quote($search_string, 'text') . ' OR records.content LIKE ' . $this->db->quote($search_string, 'text') .
            ($reverse ? ' OR records.name LIKE ' . $reverse_search_string . ' OR records.content LIKE ' . $reverse_search_string : '') . ')' .
            ($permission_view == 'own' ? 'AND z.owner = ' . $this->db->quote($_SESSION['userid'], 'integer') : '') .
            ($iface_search_group_records ? ' GROUP BY records.name, records.content ' : '') . // May not work correctly with MySQL strict mode
            ' ORDER BY ' . $sort_records_by .
            ' LIMIT ' . $iface_rowamount . ' OFFSET ' . $offset;

        $recordsResponse = $this->db->query($recordsQuery);

        while ($record = $recordsResponse->fetch()) {
            $found_record = $record;
            $found_record['name'] = idn_to_utf8($found_record['name'], IDNA_NONTRANSITIONAL_TO_ASCII);
            $foundRecords[] = $found_record;
        }

        return $foundRecords;
    }
}
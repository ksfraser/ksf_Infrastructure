<?php
/**
 * GEDCOM Import FA Adapter
 *
 * FA-specific adapter that implements the GEDCOM library's repository
 * contracts against FA's database layer, then delegates to the
 * framework-agnostic ImportService from ksfraser/gedcom.
 *
 * @package ksf_FA_CRM
 * @since 2.0.0
 */

use Ksfraser\CRM\GEDCOM\Contract\EventRepositoryInterface;
use Ksfraser\CRM\GEDCOM\Contract\PersonRepositoryInterface;
use Ksfraser\CRM\GEDCOM\Contract\RelationshipRepositoryInterface;
use Ksfraser\CRM\GEDCOM\GedcomParser;
use Ksfraser\CRM\GEDCOM\ImportResult;
use Ksfraser\CRM\GEDCOM\ImportService;

/**
 * FA database adapter for the PersonRepository contract.
 *
 * @since 2.0.0
 */
class FaPersonRepository implements PersonRepositoryInterface
{
    /**
     * Find an existing person by GEDCOM xref or create a new one.
     *
     * @param string $xref       GEDCOM cross-reference tag (e.g. "I1")
     * @param string $firstName  First name
     * @param string $lastName   Last name
     * @param string $sex        Sex code (M/F)
     * @param string $notes      Notes text
     * @return int               Person ID
     * @throws RuntimeException  On DB error
     * @since 2.0.0
     */
    public function findOrCreate(
        string $xref,
        string $firstName,
        string $lastName,
        string $sex,
        string $notes
    ): int {
        $ref = 'GED_' . $xref;
        $fullName = trim($firstName . ' ' . $lastName);
        if ($fullName === '') {
            $fullName = $xref;
        }

        $sql = "SELECT id FROM " . TB_PREF . "crm_persons WHERE ref = " . db_escape($ref);
        $result = db_query($sql, "could not check for existing person");
        $row = db_fetch($result);
        if ($row) {
            return (int)$row['id'];
        }

        $sql = "INSERT INTO " . TB_PREF . "crm_persons (ref, name, name2, notes, inactive)
            VALUES ("
            . db_escape($ref) . ", "
            . db_escape($fullName) . ", "
            . db_escape($sex) . ", "
            . db_escape($notes) . ", 0)";
        db_query($sql, "could not create person");

        return (int)db_insert_id();
    }

    /**
     * Find a person row by ID.
     *
     * @param int $id  Person ID
     * @return array|null  Row array or null if not found
     * @since 2.0.0
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM " . TB_PREF . "crm_persons WHERE id = " . db_escape($id);
        $result = db_query($sql, "could not get person");
        $row = db_fetch($result);
        return $row ?: null;
    }

    /**
     * Return all active persons.
     *
     * @return array  Array of person rows
     * @since 2.0.0
     */
    public function findAll(): array
    {
        $rows = [];
        $sql = "SELECT * FROM " . TB_PREF . "crm_persons WHERE !inactive ORDER BY id";
        $result = db_query($sql, "could not get all persons");
        while ($row = db_fetch($result)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Return all persons related to a given person through contact relationships.
     *
     * @param int $personId  Person ID
     * @return array  Array of person rows
     * @since 2.0.0
     */
    public function findRelated(int $personId): array
    {
        $rows = [];
        $sql = "SELECT DISTINCT p.* FROM " . TB_PREF . "crm_persons p
            JOIN " . TB_PREF . "fa_crm_contact_relationships r
              ON p.id = r.person_a_id OR p.id = r.person_b_id
            WHERE (r.person_a_id = " . db_escape($personId) . "
               OR r.person_b_id = " . db_escape($personId) . ")
              AND p.id != " . db_escape($personId);
        $result = db_query($sql, "could not get related persons");
        while ($row = db_fetch($result)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Find a person by their GEDCOM xref ref field.
     *
     * @param string $xref  GEDCOM xref (e.g. "I1" — stored as "GED_I1")
     * @return array|null   Row array or null if not found
     * @since 2.0.0
     */
    public function findByXref(string $xref): ?array
    {
        $ref = 'GED_' . $xref;
        $sql = "SELECT * FROM " . TB_PREF . "crm_persons WHERE ref = " . db_escape($ref);
        $result = db_query($sql, "could not find person by xref");
        $row = db_fetch($result);
        return $row ?: null;
    }
}

/**
 * FA database adapter for the RelationshipRepository contract.
 *
 * @since 2.0.0
 */
class FaRelationshipRepository implements RelationshipRepositoryInterface
{
    /**
     * Create a contact relationship record.
     *
     * @param int         $personAId  First person ID
     * @param int         $personBId  Second person ID
     * @param string      $type       Relationship type (e.g. 'spouse', 'parent', 'child')
     * @param string|null $startDate  Optional start date (Y-m-d)
     * @param string|null $endDate    Optional end date (Y-m-d)
     * @param string|null $details    Optional free-form details
     * @return int  New relationship ID
     * @since 2.0.0
     */
    public function create(
        int $personAId,
        int $personBId,
        string $type,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $details = null
    ): int {
        return (int)insert_contact_relationship(
            $personAId,
            $personBId,
            $type,
            0,
            $startDate,
            $endDate,
            $details ?? 'GEDCOM import'
        );
    }

    /**
     * Find all relationships involving a person.
     *
     * @param int $personId  Person ID
     * @return array  Array of relationship rows
     * @since 2.0.0
     */
    public function findByPerson(int $personId): array
    {
        $rows = [];
        $sql = "SELECT * FROM " . TB_PREF . "fa_crm_contact_relationships
            WHERE person_a_id = " . db_escape($personId) . "
               OR person_b_id = " . db_escape($personId) . "
            ORDER BY relation_type, id";
        $result = db_query($sql, "could not get relationships for person");
        while ($row = db_fetch($result)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Find all relationships where either party is in the given ID list.
     *
     * @param int[] $personIds  List of person IDs
     * @return array  Array of relationship rows
     * @since 2.0.0
     */
    public function findByPersons(array $personIds): array
    {
        if (empty($personIds)) {
            return [];
        }
        $escaped = array_map('db_escape', $personIds);
        $in = implode(',', $escaped);

        $rows = [];
        $sql = "SELECT * FROM " . TB_PREF . "fa_crm_contact_relationships
            WHERE person_a_id IN ($in) OR person_b_id IN ($in)
            ORDER BY relation_type, id";
        $result = db_query($sql, "could not get relationships for persons");
        while ($row = db_fetch($result)) {
            $rows[] = $row;
        }
        return $rows;
    }
}

/**
 * FA database adapter for the EventRepository contract.
 *
 * @since 2.0.0
 */
class FaEventRepository implements EventRepositoryInterface
{
    /**
     * Create a life event record.
     *
     * @param string      $entityType  Entity type ('person', 'account', 'relationship')
     * @param int         $entityId    Entity ID
     * @param string      $eventType   GEDCOM tag or custom event type
     * @param string|null $eventDate   Optional date (Y-m-d)
     * @param string|null $eventPlace  Optional place description
     * @param string|null $gedcomTag   Optional original GEDCOM tag for round-trip fidelity
     * @param string|null $details     Optional JSON details
     * @return int  New event ID
     * @since 2.0.0
     */
    public function create(
        string $entityType,
        int $entityId,
        string $eventType,
        ?string $eventDate = null,
        ?string $eventPlace = null,
        ?string $gedcomTag = null,
        ?string $details = null
    ): int {
        return (int)insert_life_event(
            $entityId,
            $eventType,
            $eventDate,
            $eventPlace,
            null,
            $gedcomTag ?? $eventType,
            $details
        );
    }

    /**
     * Find all events for a given entity.
     *
     * @param string $entityType  Entity type ('person', 'account', 'relationship')
     * @param int    $entityId    Entity ID
     * @return array  Array of event rows
     * @since 2.0.0
     */
    public function findByEntity(string $entityType, int $entityId): array
    {
        $rows = [];
        $sql = "SELECT * FROM " . TB_PREF . "fa_crm_life_events
            WHERE entity_type = " . db_escape($entityType) . "
              AND entity_id = " . db_escape($entityId) . "
            ORDER BY event_date, event_type";
        $result = db_query($sql, "could not get life events");
        while ($row = db_fetch($result)) {
            $rows[] = $row;
        }
        return $rows;
    }
}

/**
 * Facade function: parse a GEDCOM string and persist via FA repositories.
 *
 * @param string $gedcomContent  Raw GEDCOM 5.5 file contents
 * @return ImportResult          Statistics about the import
 * @since 2.0.0
 */
function import_gedcom_content(string $gedcomContent): ImportResult
{
    $parser = new GedcomParser();
    $service = new ImportService(
        new FaPersonRepository(),
        new FaRelationshipRepository(),
        new FaEventRepository()
    );

    $parsed = $parser->parse($gedcomContent);
    return $service->import($parsed);
}

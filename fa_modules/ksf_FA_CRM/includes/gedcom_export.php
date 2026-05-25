<?php
/**
 * GEDCOM Export FA Adapter
 *
 * FA-specific adapter that implements the GEDCOM library's repository
 * contracts against FA's database layer, then delegates to the
 * framework-agnostic ExportService from ksfraser/gedcom.
 *
 * @package ksf_FA_CRM
 * @since 2.0.0
 */

use Ksfraser\CRM\GEDCOM\ExportService;

/**
 * Facade function: export persons from FA database as a GEDCOM 5.5 string.
 *
 * @param int|null $personId  Specific person ID, or null to export all
 * @return string             GEDCOM 5.5 formatted content
 * @since 2.0.0
 */
function export_gedcom(?int $personId = null): string
{
    // FaPersonRepository, FaRelationshipRepository and FaEventRepository
    // are declared in gedcom_import.php (loaded together by pages)
    $service = new ExportService(
        new FaPersonRepository(),
        new FaRelationshipRepository(),
        new FaEventRepository()
    );

    return $service->export($personId);
}

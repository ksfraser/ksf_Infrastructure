<?php

/**
 * CRM Email Import Service
 *
 * Imports emails from SMTP/IMAP servers and associates them with customer contacts
 */

namespace Ksfraser\FA\CRM\Services;

class EmailImportService
{
    private $imap_connection;
    private $account_data;

    public function __construct($account_data)
    {
        $this->account_data = $account_data;
    }

    /**
     * Connect to IMAP server
     */
    public function connect()
    {
        $host = $this->account_data['imap_host'];
        $port = $this->account_data['imap_port'];
        $encryption = $this->account_data['imap_encryption'];
        $username = $this->account_data['imap_username'];
        $password = $this->account_data['imap_password'];

        $mailbox = '{' . $host . ':' . $port . '/imap/' . $encryption . '}INBOX';

        $this->imap_connection = imap_open($mailbox, $username, $password);

        if (!$this->imap_connection) {
            throw new \Exception("Cannot connect to IMAP server: " . imap_last_error());
        }

        return true;
    }

    /**
     * Disconnect from IMAP server
     */
    public function disconnect()
    {
        if ($this->imap_connection) {
            imap_close($this->imap_connection);
            $this->imap_connection = null;
        }
    }

    /**
     * Import emails since last sync
     */
    public function importEmails()
    {
        if (!$this->imap_connection) {
            $this->connect();
        }

        $emails = array();
        $last_sync = $this->account_data['last_sync'];

        if ($last_sync) {
            $search_criteria = 'SINCE "' . date('d-M-Y', strtotime($last_sync)) . '"';
        } else {
            $search_criteria = 'UNSEEN';
        }

        $email_ids = imap_search($this->imap_connection, $search_criteria);

        if ($email_ids) {
            foreach ($email_ids as $email_id) {
                $email_data = $this->getEmailData($email_id);
                if ($email_data) {
                    $emails[] = $email_data;
                }
            }
        }

        return $emails;
    }

    /**
     * Get email data from IMAP
     */
    private function getEmailData($email_id)
    {
        $header = imap_headerinfo($this->imap_connection, $email_id);
        $body = imap_body($this->imap_connection, $email_id);

        $from = $this->extractEmailAddress($header->fromaddress);
        $to = $this->extractEmailAddress($header->toaddress);

        $contact = $this->findContactByEmail($from, $to);

        if (!$contact) {
            return null;
        }

        $email_data = array(
            'message_id' => $header->message_id,
            'subject' => $header->subject,
            'from_email' => $from,
            'to_email' => $to,
            'body' => $body,
            'received_date' => date('Y-m-d H:i:s', $header->udate),
            'contact_id' => $contact['id'],
            'debtor_no' => $contact['debtor_no']
        );

        $ics_attachments = $this->getICSParts($email_id);
        if (!empty($ics_attachments)) {
            $email_data['ics_attachments'] = $ics_attachments;
        }

        return $email_data;
    }

    private function extractEmailAddress($address_string)
    {
        if (preg_match('/<([^>]+)>/', $address_string, $matches)) {
            return $matches[1];
        }
        return trim($address_string);
    }

    private function findContactByEmail($from_email, $to_email)
    {
        global $db;

        $sql = "SELECT c.id, c.debtor_no FROM " . TB_PREF . "fa_crm_contacts c
                WHERE c.email = " . db_escape($from_email) . " AND c.inactive = 0
                LIMIT 1";
        $result = db_query($sql);
        if ($contact = db_fetch($result)) {
            return $contact;
        }

        $sql = "SELECT c.id, c.debtor_no FROM " . TB_PREF . "fa_crm_contacts c
                WHERE c.email = " . db_escape($to_email) . " AND c.inactive = 0
                LIMIT 1";
        $result = db_query($sql);
        if ($contact = db_fetch($result)) {
            return $contact;
        }

        return null;
    }

    public function saveEmailAsCommunication($email_data)
    {
        $communication_data = array(
            'debtor_no' => $email_data['debtor_no'],
            'contact_id' => $email_data['contact_id'],
            'communication_type' => 'email',
            'direction' => 'inbound',
            'subject' => $email_data['subject'],
            'message' => $email_data['body'],
            'email_from' => $email_data['from_email'],
            'email_to' => $email_data['to_email'],
            'status' => 'completed',
            'completed_date' => $email_data['received_date'],
            'email_message_id' => $email_data['message_id'],
            'created_by' => 'email_import'
        );

        $communication_id = add_communication($communication_data);

        if (isset($email_data['ics_attachments'])) {
            $processed_meetings = $this->processICSAttachments($email_data);
            if (!empty($processed_meetings)) {
                error_log("Processed " . count($processed_meetings) . " meetings from ICS attachments in email: " . $email_data['subject']);
            }
        }

        return $communication_id;
    }

    public static function processEmailImport($account_id)
    {
        $account = get_email_account($account_id);
        if (!$account) {
            throw new \Exception("Email account not found");
        }

        $service = new self($account);
        $emails = $service->importEmails();

        $imported_count = 0;
        foreach ($emails as $email) {
            $service->saveEmailAsCommunication($email);
            $imported_count++;
        }

        $service->disconnect();

        update_email_sync_time($account_id);

        return $imported_count;
    }

    private function getICSParts($email_id)
    {
        $ics_parts = array();

        $structure = imap_fetchstructure($this->imap_connection, $email_id);

        if (isset($structure->parts)) {
            $ics_parts = $this->parseParts($structure->parts, $email_id);
        } elseif ($structure->subtype == 'CALENDAR' || $this->isICSEmail($email_id)) {
            $ics_content = imap_body($this->imap_connection, $email_id);
            if ($this->isValidICS($ics_content)) {
                $ics_parts[] = array(
                    'filename' => 'meeting.ics',
                    'content' => $ics_content,
                    'type' => 'text/calendar'
                );
            }
        }

        return $ics_parts;
    }

    private function parseParts($parts, $email_id, $part_number = '')
    {
        $ics_parts = array();

        foreach ($parts as $index => $part) {
            $current_part = $part_number . ($part_number ? '.' : '') . ($index + 1);

            if (isset($part->parts)) {
                $ics_parts = array_merge($ics_parts, $this->parseParts($part->parts, $email_id, $current_part));
            } else {
                $content_type = $this->getContentType($part);
                $filename = $this->getFilename($part);

                if ($this->isICSType($content_type, $filename)) {
                    $content = imap_fetchbody($this->imap_connection, $email_id, $current_part);
                    $content = $this->decodeContent($content, $part->encoding);

                    if ($this->isValidICS($content)) {
                        $ics_parts[] = array(
                            'filename' => $filename ?: 'meeting.ics',
                            'content' => $content,
                            'type' => $content_type
                        );
                    }
                }
            }
        }

        return $ics_parts;
    }

    private function getContentType($part)
    {
        if (isset($part->type) && isset($part->subtype)) {
            return $part->type . '/' . $part->subtype;
        }
        return '';
    }

    private function getFilename($part)
    {
        if (isset($part->dparameters)) {
            foreach ($part->dparameters as $param) {
                if (strtolower($param->attribute) == 'filename') {
                    return $param->value;
                }
            }
        }
        return null;
    }

    private function isICSType($content_type, $filename)
    {
        $ics_types = array('text/calendar', 'application/ics', 'text/x-vcalendar');
        $ics_extensions = array('.ics', '.ical', '.icalendar');

        if (in_array(strtolower($content_type), $ics_types)) {
            return true;
        }

        if ($filename) {
            foreach ($ics_extensions as $ext) {
                if (stripos($filename, $ext) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isICSEmail($email_id)
    {
        $body = imap_body($this->imap_connection, $email_id);
        return $this->isValidICS($body);
    }

    private function isValidICS($content)
    {
        return strpos($content, 'BEGIN:VCALENDAR') !== false &&
               strpos($content, 'END:VCALENDAR') !== false;
    }

    private function decodeContent($content, $encoding)
    {
        switch ($encoding) {
            case 3:
                return base64_decode($content);
            case 4:
                return quoted_printable_decode($content);
            default:
                return $content;
        }
    }

    public function processICSAttachments($email_data)
    {
        if (!isset($email_data['ics_attachments'])) {
            return array();
        }

        $processed_meetings = array();

        foreach ($email_data['ics_attachments'] as $ics_attachment) {
            $ics_events = $this->parseICSContent($ics_attachment['content']);

            foreach ($ics_events as $event) {
                $meeting_id = $this->createOrUpdateMeetingFromICS($event, $email_data);
                if ($meeting_id) {
                    $processed_meetings[] = $meeting_id;
                }
            }
        }

        return $processed_meetings;
    }

    private function parseICSContent($ics_content)
    {
        $events = array();
        $lines = explode("\n", $ics_content);

        $current_event = null;
        $in_event = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line == 'BEGIN:VEVENT') {
                $current_event = array();
                $in_event = true;
            } elseif ($line == 'END:VEVENT') {
                if ($current_event) {
                    $events[] = $current_event;
                }
                $current_event = null;
                $in_event = false;
            } elseif ($in_event && strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);

                if (strpos($key, ';') !== false) {
                    list($key, $params) = explode(';', $key, 2);
                }

                switch (strtoupper($key)) {
                    case 'SUMMARY':
                        $current_event['summary'] = $value;
                        break;
                    case 'DESCRIPTION':
                        $current_event['description'] = str_replace('\\n', "\n", $value);
                        break;
                    case 'DTSTART':
                        $current_event['dtstart'] = $this->parseICSDateTime($value);
                        break;
                    case 'DTEND':
                        $current_event['dtend'] = $this->parseICSDateTime($value);
                        break;
                    case 'LOCATION':
                        $current_event['location'] = $value;
                        break;
                    case 'UID':
                        $current_event['uid'] = $value;
                        break;
                    case 'ORGANIZER':
                        $current_event['organizer'] = $this->extractEmailFromICS($value);
                        break;
                    case 'ATTENDEE':
                        if (!isset($current_event['attendees'])) {
                            $current_event['attendees'] = array();
                        }
                        $current_event['attendees'][] = $this->extractEmailFromICS($value);
                        break;
                    case 'STATUS':
                        $current_event['status'] = strtolower($value);
                        break;
                }
            }
        }

        return $events;
    }

    private function parseICSDateTime($datetime_str)
    {
        if (strlen($datetime_str) == 8) {
            return date('Y-m-d H:i:s', strtotime($datetime_str));
        } elseif (strlen($datetime_str) == 16) {
            return date('Y-m-d H:i:s', strtotime($datetime_str));
        } elseif (strpos($datetime_str, 'Z') !== false) {
            return date('Y-m-d H:i:s', strtotime($datetime_str));
        } else {
            return date('Y-m-d H:i:s', strtotime($datetime_str));
        }
    }

    private function extractEmailFromICS($ics_field)
    {
        if (preg_match('/mailto:([^\s]+)/i', $ics_field, $matches)) {
            return $matches[1];
        }
        return $ics_field;
    }

    private function createOrUpdateMeetingFromICS($event, $email_data)
    {
        $existing_meeting = $this->findMeetingByICSUID($event['uid']);

        $meeting_data = array(
            'meeting_name' => $event['summary'] ?: 'Imported Meeting',
            'meeting_type' => 'meeting',
            'description' => $event['description'] ?: '',
            'start_date' => $event['dtstart'],
            'end_date' => $event['dtend'] ?: date('Y-m-d H:i:s', strtotime($event['dtstart']) + 3600),
            'duration_minutes' => $this->calculateDuration($event['dtstart'], $event['dtend']),
            'time_zone' => 'UTC',
            'location_type' => $event['location'] ? 'physical' : 'virtual',
            'custom_location' => $event['location'] ?: '',
            'debtor_no' => $email_data['debtor_no'],
            'status' => $this->mapICSStatus($event['status'] ?: 'confirmed'),
            'priority' => 'normal',
            'assigned_to' => $this->findEmployeeByEmail($event['organizer']),
            'created_by' => 'email_import',
            'ics_uid' => $event['uid'],
            'external_id' => $event['uid']
        );

        if ($existing_meeting) {
            update_meeting($existing_meeting['id'], $meeting_data);
            $meeting_id = $existing_meeting['id'];
        } else {
            $meeting_id = add_meeting($meeting_data);
        }

        if (isset($event['attendees']) && is_array($event['attendees'])) {
            $this->addMeetingAttendeesFromICS($meeting_id, $event['attendees'], $email_data);
        }

        return $meeting_id;
    }

    private function findMeetingByICSUID($ics_uid)
    {
        global $db;

        $sql = "SELECT * FROM " . TB_PREF . "crm_meetings WHERE ics_uid = " . db_escape($ics_uid) . " LIMIT 1";
        $result = db_query($sql);
        return db_fetch($result);
    }

    private function calculateDuration($start, $end)
    {
        $start_time = strtotime($start);
        $end_time = strtotime($end ?: date('Y-m-d H:i:s', $start_time + 3600));
        return round(($end_time - $start_time) / 60);
    }

    private function mapICSStatus($ics_status)
    {
        $status_map = array(
            'confirmed' => 'confirmed',
            'tentative' => 'planned',
            'cancelled' => 'cancelled'
        );

        return isset($status_map[$ics_status]) ? $status_map[$ics_status] : 'planned';
    }

    private function findEmployeeByEmail($email)
    {
        return null;
    }

    private function addMeetingAttendeesFromICS($meeting_id, $attendee_emails, $email_data)
    {
        foreach ($attendee_emails as $email) {
            if (strtolower($email) == strtolower($email_data['from_email'])) {
                continue;
            }

            $contact = $this->findContactByEmail($email, '');
            if ($contact) {
                $attendee_data = array(
                    'attendee_type' => 'contact',
                    'contact_id' => $contact['id'],
                    'attendee_role' => 'required',
                    'response_status' => 'pending'
                );
            } else {
                $attendee_data = array(
                    'attendee_type' => 'external',
                    'external_name' => '',
                    'external_email' => $email,
                    'attendee_role' => 'required',
                    'response_status' => 'pending'
                );
            }

            add_meeting_attendee($meeting_id, $attendee_data);
        }
    }
}

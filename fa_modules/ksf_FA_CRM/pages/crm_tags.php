<?php
$page_security = 'SA_CRM_TAGS';
$path_to_root = "../../..";
include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/admin/db/tags_db.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_tags.inc");

$type = get_post('type', TAG_CUSTOMER);
$page_title = _("CRM Tags") . " - " . crm_tag_type_name($type);

page($page_title, false, false, "", "");

simple_page_mode(true);

if ($Mode == 'DELETE_ITEM') {
    $sql = "SELECT COUNT(*) FROM " . TB_PREF . "tag_associations ta
            INNER JOIN " . TB_PREF . "tags t ON t.id = ta.tag_id
            WHERE ta.tag_id = " . db_escape($selected_id) . " AND t.type = " . db_escape($type);
    $result = db_query($sql, "could not count tag associations");
    $count = db_fetch_row($result);

    if ($count[0] > 0) {
        display_error(_("Cannot delete this tag. It is currently in use."));
    } else {
        delete_tag($selected_id);
        display_notification(_("Tag has been deleted."));
    }
    $Mode = false;
}

if ($Mode == 'ADD_ITEM' || $Mode == 'UPDATE_ITEM') {
    if (strlen($_POST['name']) == 0) {
        display_error(_("The tag name cannot be empty."));
        set_focus('name');
    } else {
        if ($selected_id != -1) {
            update_tag($selected_id, $_POST['name'], $_POST['description']);
            display_notification(_("Tag has been updated."));
        } else {
            add_tag($type, $_POST['name'], $_POST['description']);
            display_notification(_("New tag has been added."));
        }
        $Mode = false;
    }
}

start_form();
start_table(TABLESTYLE_NOBORDER);

label_cell(_("Tag Type:"));
$type_options = array(
    TAG_CUSTOMER    => crm_tag_type_name(TAG_CUSTOMER),
    TAG_CONTACT     => crm_tag_type_name(TAG_CONTACT),
    TAG_OPPORTUNITY => crm_tag_type_name(TAG_OPPORTUNITY),
    TAG_LEAD        => crm_tag_type_name(TAG_LEAD),
    TAG_COMMUNICATION => crm_tag_type_name(TAG_COMMUNICATION),
);
$type_selector = array_selector('type', $type, $type_options, array('select_submit' => true));
cell($type_selector);
end_row();
end_table();
end_form();

if ($selected_id != -1) {
    $tag = get_tag($selected_id);
    $_POST['name'] = $tag['name'];
    $_POST['description'] = $tag['description'];
}

start_form();
start_table(TABLESTYLE2);

text_row(_("Tag Name:"), 'name', null, 30, 30);
text_row(_("Description:"), 'description', null, 50, 60);

end_table(1);
submit_add_or_update_center($selected_id == -1, '', 'both');
end_form();

br();

start_table(TABLESTYLE);
$th = array(_("Name"), _("Description"), _("Assigned"), "", "");
table_header($th);

$tags = get_tags($type, true);
$k = 0;
while ($tag = db_fetch($tags)) {
    alt_table_row_color($k);

    $sql = "SELECT COUNT(*) FROM " . TB_PREF . "tag_associations WHERE tag_id = " . db_escape($tag['id']);
    $cnt_result = db_query($sql, "could not count");
    $cnt = db_fetch_row($cnt_result);

    label_cell($tag['name']);
    label_cell($tag['description']);
    label_cell($cnt[0]);
    edit_button_cell("Edit" . $tag['id'], _("Edit"));
    if ($cnt[0] == 0) {
        delete_button_cell("Delete" . $tag['id'], _("Delete"));
    } else {
        label_cell("");
    }
    end_row();
}

end_table(1);
page_end();

<?php
$page_security = 'SA_CRM_ORG_CHART';
$path_to_root = "../../..";

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/includes/db.inc");
include_once($path_to_root . "/admin/db/tags_db.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_relationships_db.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_tags.inc");

page(_("CRM Org Chart"), false, false, "", "");

// =============================================================================
// DATA FETCH
// =============================================================================

$nodes = array();
$edges = array();
$tag_index = array();

foreach (crm_tag_types() as $t) {
    $result = get_tags($t);
    while ($row = db_fetch($result)) {
        $tag_index[$row['id']] = $row;
    }
}

// 1) Accounts
$acc_result = db_query("SELECT debtor_no, name, debtor_ref, address, phone, email, inactive FROM " . TB_PREF . "debtors_master ORDER BY name");
while ($acc = db_fetch($acc_result)) {
    $tags = array();
    $tr = get_tags_associated_with_record(TAG_CUSTOMER, $acc['debtor_no']);
    while ($t = db_fetch($tr)) {
        $tags[] = $t;
    }
    $nodes[] = array(
        'id' => 'account_' . $acc['debtor_no'],
        'label' => $acc['name'],
        'group' => 'account',
        'title' => $acc['name'] . ($acc['debtor_ref'] ? ' (' . $acc['debtor_ref'] . ')' : ''),
        'data' => array(
            'type' => 'account',
            'debtor_no' => $acc['debtor_no'],
            'debtor_ref' => $acc['debtor_ref'],
            'name' => $acc['name'],
            'phone' => $acc['phone'],
            'email' => $acc['email'],
            'inactive' => $acc['inactive'],
        ),
        'tags' => $tags,
    );
}

// 2) Persons
$per_result = db_query("SELECT id, ref, name, phone, email, notes, inactive FROM " . TB_PREF . "crm_persons ORDER BY name");
while ($per = db_fetch($per_result)) {
    $tags = array();
    $tr = get_tags_associated_with_record(TAG_CONTACT, $per['id']);
    while ($t = db_fetch($tr)) {
        $tags[] = $t;
    }
    $nodes[] = array(
        'id' => 'person_' . $per['id'],
        'label' => $per['name'],
        'group' => 'person',
        'title' => $per['name'],
        'data' => array(
            'type' => 'person',
            'id' => $per['id'],
            'ref' => $per['ref'],
            'name' => $per['name'],
            'phone' => $per['phone'],
            'email' => $per['email'],
            'inactive' => $per['inactive'],
        ),
        'tags' => $tags,
    );
}

function rel_color($t) {
    $map = array(
        'spouse' => '#e74c3c',
        'parent' => '#2ecc71',
        'child' => '#2ecc71',
        'sibling' => '#3498db',
        'owns' => '#f39c12',
        'subsidiary' => '#f39c12',
        'trustee_of' => '#9b59b6',
        'beneficiary_of' => '#9b59b6',
        'trustee' => '#9b59b6',
        'beneficiary' => '#9b59b6',
        'director' => '#1abc9c',
        'employee' => '#1abc9c',
        'owner' => '#1abc9c',
        'signatory' => '#1abc9c',
        'accountant' => '#1abc9c',
        'legal_contact' => '#1abc9c',
    );
    return isset($map[$t]) ? $map[$t] : '#95a5a6';
}

function rel_label($t) {
    $map = array(
        'spouse' => 'Spouse',
        'parent' => 'Parent',
        'child' => 'Child',
        'sibling' => 'Sibling',
        'owns' => 'Owns',
        'subsidiary' => 'Subsidiary',
        'trustee_of' => 'Trustee Of',
        'beneficiary_of' => 'Beneficiary Of',
        'trustee' => 'Trustee',
        'beneficiary' => 'Beneficiary',
        'director' => 'Director',
        'employee' => 'Employee',
        'owner' => 'Owner',
        'signatory' => 'Signatory',
        'accountant' => 'Accountant',
        'legal_contact' => 'Legal Contact',
    );
    return isset($map[$t]) ? $map[$t] : $t;
}

// 3) Contact relationships (person-to-person)
$cr_result = db_query("SELECT r.*, p_a.name AS a_name, p_b.name AS b_name
    FROM " . TB_PREF . "fa_crm_contact_relationships r
    LEFT JOIN " . TB_PREF . "crm_persons p_a ON r.person_a_id = p_a.id
    LEFT JOIN " . TB_PREF . "crm_persons p_b ON r.person_b_id = p_b.id
    ORDER BY r.id");
while ($cr = db_fetch($cr_result)) {
    $from = 'person_' . $cr['person_a_id'];
    $to = 'person_' . $cr['person_b_id'];
    $edges[] = array(
        'from' => $from,
        'to' => $to,
        'label' => rel_label($cr['relation_type']),
        'color' => array('color' => rel_color($cr['relation_type'])),
        'dashes' => $cr['end_date'] ? true : false,
        'arrows' => $cr['is_directed'] ? 'to' : 'undefined',
        'title' => $cr['notes'] ?: rel_label($cr['relation_type']),
        'relation_type' => $cr['relation_type'],
    );
}

// 4) Account relationships (account-to-account)
$ar_result = db_query("SELECT * FROM " . TB_PREF . "fa_crm_account_relationships ORDER BY id");
while ($ar = db_fetch($ar_result)) {
    $from = 'account_' . $ar['parent_debtor_no'];
    $to = 'account_' . $ar['child_debtor_no'];
    $label = rel_label($ar['relation_type']);
    if ($ar['ownership_pct']) {
        $label .= ' ' . $ar['ownership_pct'] . '%';
    }
    $edges[] = array(
        'from' => $from,
        'to' => $to,
        'label' => $label,
        'color' => array('color' => rel_color($ar['relation_type'])),
        'dashes' => $ar['end_date'] ? true : false,
        'arrows' => 'to',
        'title' => $ar['notes'] ?: rel_label($ar['relation_type']),
        'relation_type' => $ar['relation_type'],
    );
}

// 5) Person-account roles
$pr_result = db_query("SELECT r.*, p.name AS person_name, d.name AS account_name
    FROM " . TB_PREF . "fa_crm_person_account_roles r
    LEFT JOIN " . TB_PREF . "crm_persons p ON r.person_id = p.id
    LEFT JOIN " . TB_PREF . "debtors_master d ON r.debtor_no = d.debtor_no
    ORDER BY r.id");
while ($pr = db_fetch($pr_result)) {
    $from = 'person_' . $pr['person_id'];
    $to = 'account_' . $pr['debtor_no'];
    $label = rel_label($pr['role']);
    $edges[] = array(
        'from' => $from,
        'to' => $to,
        'label' => $label,
        'color' => array('color' => rel_color($pr['role'])),
        'dashes' => $pr['end_date'] ? true : false,
        'arrows' => 'to',
        'title' => $pr['notes'] ?: rel_label($pr['role']),
        'relation_type' => $pr['role'],
    );
}

// Collect unique tags across all nodes for filter bar
$all_tags_used = array();
foreach ($nodes as &$n) {
    foreach ($n['tags'] as $t) {
        $tid = $t['id'];
        if (!isset($all_tags_used[$tid])) {
            $all_tags_used[$tid] = $t;
        }
    }
}
unset($n);

$graph_json = array(
    'nodes' => json_encode($nodes),
    'edges' => json_encode($edges),
    'tags' => json_encode(array_values($all_tags_used)),
);
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/vis-network/9.1.6/standalone/umd/vis-network.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/vis-network/9.1.6/styles/vis-network.min.css" rel="stylesheet">

<style>
#org-chart { width: 100%; height: 72vh; border: 1px solid #ddd; background: #fafafa; }
.controls { margin-bottom: 12px; display: flex; gap: 20px; flex-wrap: wrap; align-items: center; }
.controls label { font-weight: bold; }
.tag-filter { display: inline-block; margin: 3px; padding: 3px 10px; border-radius: 12px; cursor: pointer; font-size: 12px; border: 2px solid transparent; user-select: none; }
.tag-filter.active { border-color: #333; }
.tag-filter.inactive { opacity: 0.4; }
#search-box { padding: 4px 8px; border: 1px solid #ccc; border-radius: 4px; }
#detail-panel { display: none; position: fixed; right: 20px; top: 100px; width: 320px; max-height: 70vh; overflow-y: auto; background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; }
#detail-panel h3 { margin: 0 0 8px 0; }
#detail-panel .close { float: right; cursor: pointer; font-size: 20px; color: #999; }
#detail-panel .close:hover { color: #333; }
#detail-panel table { width: 100%; }
#detail-panel td { padding: 3px 6px; vertical-align: top; }
#detail-panel td:first-child { font-weight: bold; width: 90px; color: #555; }
.tag-badge { display: inline-block; padding: 1px 7px; border-radius: 8px; font-size: 11px; margin: 1px; color: #fff; }
.vis-network { outline: none; }
</style>

<div class="controls">
  <label>View:
    <select id="view-mode">
      <option value="all">All Relationships</option>
      <option value="accounts">Account Hierarchies</option>
      <option value="persons">Personal Relationships</option>
    </select>
  </label>
  <label>Tags:
    <span id="tag-filters"></span>
  </label>
  <label>Search: <input id="search-box" placeholder="Type to filter..."></label>
</div>

<div id="org-chart"></div>

<div id="detail-panel"></div>

<script>
var nodes = new vis.DataSet(<?php echo $graph_json['nodes']; ?>);
var edges = new vis.DataSet(<?php echo $graph_json['edges']; ?>);
var allTags = <?php echo $graph_json['tags']; ?>;

var container = document.getElementById('org-chart');
var data = { nodes: nodes, edges: edges };
var options = {
  physics: {
    solver: 'forceAtlas2Based',
    forceAtlas2Based: { gravitationalConstant: -40, springConstant: 0.02, springLength: 160, damping: 0.4 },
    stabilization: { iterations: 200 },
  },
  edges: {
    smooth: { type: 'continuous' },
    font: { size: 10, align: 'middle' },
    width: 1.5,
  },
  nodes: {
    shape: 'box',
    font: { size: 13, face: 'Arial' },
    borderWidth: 2,
    borderWidthSelected: 3,
    margin: { top: 6, bottom: 6, left: 10, right: 10 },
  },
  groups: {
    account: { color: { background: '#d4efdf', border: '#1e8449' }, shape: 'box', font: { color: '#1e8449' } },
    person: { color: { background: '#d6eaf8', border: '#2e86c1' }, shape: 'ellipse', font: { color: '#2e86c1' } },
  },
  interaction: {
    hover: true,
    tooltipDelay: 150,
    navigationButtons: true,
    keyboard: true,
  },
  layout: { improvedLayout: true },
};
var network = new vis.Network(container, data, options);

var nodeData = {};
nodes.forEach(function (n) { nodeData[n.id] = n; });

// =============================================================================
// TAG FILTER BAR
// =============================================================================
var tagFilterContainer = document.getElementById('tag-filters');
var activeTagFilters = {};

var tagColors = [
  '#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6',
  '#1abc9c', '#e67e22', '#1f618d', '#117a65', '#7d3c98',
  '#a93226', '#2471a3', '#1e8449', '#b7950b', '#6c3483',
];

allTags.forEach(function (tag, i) {
  activeTagFilters[tag.id] = true;
  var span = document.createElement('span');
  span.className = 'tag-filter active';
  span.style.backgroundColor = tagColors[i % tagColors.length];
  span.style.color = '#fff';
  span.textContent = tag.name;
  span.dataset.tagId = tag.id;
  span.addEventListener('click', function () {
    var id = parseInt(this.dataset.tagId);
    activeTagFilters[id] = !activeTagFilters[id];
    this.classList.toggle('active');
    this.classList.toggle('inactive');
    applyFilters();
  });
  tagFilterContainer.appendChild(span);
});

// =============================================================================
// VIEW MODE
// =============================================================================
var viewMode = 'all';
document.getElementById('view-mode').addEventListener('change', function () {
  viewMode = this.value;
  applyFilters();
});

// =============================================================================
// SEARCH
// =============================================================================
var searchTerm = '';
document.getElementById('search-box').addEventListener('input', function () {
  searchTerm = this.value.toLowerCase();
  applyFilters();
});

// =============================================================================
// FILTER LOGIC
// =============================================================================
function applyFilters() {
  var visibleNodeIds = [];
  nodes.forEach(function (n) {
    var d = n.data || {};

    if (viewMode === 'accounts' && d.type !== 'account') return;
    if (viewMode === 'persons' && d.type !== 'person') return;

    if (searchTerm && n.label.toLowerCase().indexOf(searchTerm) === -1) {
      var found = false;
      edges.forEach(function (e) {
        if (e.from === n.id || e.to === n.id) {
          var otherId = e.from === n.id ? e.to : e.from;
          var other = nodeData[otherId];
          if (other && other.label && other.label.toLowerCase().indexOf(searchTerm) !== -1) {
            found = true;
          }
        }
      });
      if (!found) return;
    }

    var anyActive = Object.values(activeTagFilters).some(function (v) { return v; });
    if (anyActive) {
      var nodeTags = n.tags || [];
      var hasActive = nodeTags.some(function (t) { return activeTagFilters[t.id]; });
      if (!hasActive) return;
    }

    visibleNodeIds.push(n.id);
  });

  var visibleSet = {};
  visibleNodeIds.forEach(function (id) { visibleSet[id] = true; });

  var visibleEdgeIds = [];
  edges.forEach(function (e) {
    if (visibleSet[e.from] && visibleSet[e.to]) {
      visibleEdgeIds.push(e.id);
    }
  });

  nodes.forEach(function (n) {
    nodes.update({ id: n.id, hidden: !visibleSet[n.id] });
  });

  edges.forEach(function (e) {
    edges.update({ id: e.id, hidden: visibleEdgeIds.indexOf(e.id) === -1 });
  });
}

// =============================================================================
// DETAIL PANEL
// =============================================================================
function showDetail(nodeId) {
  var n = nodeData[nodeId];
  if (!n) return;
  var d = n.data || {};
  var panel = document.getElementById('detail-panel');

  var tagsHtml = '';
  if (n.tags && n.tags.length) {
    n.tags.forEach(function (t, i) {
      tagsHtml += '<span class="tag-badge" style="background:' + tagColors[i % tagColors.length] + '">' + t.name + '</span>';
    });
  }

  var html = '<span class="close" onclick="this.parentElement.style.display=\'none\'">&times;</span>';
  html += '<h3>' + n.label + '</h3>';
  html += '<table>';

  if (d.type === 'account') {
    html += '<tr><td>Type</td><td>Account</td></tr>';
    html += '<tr><td>Ref</td><td>' + (d.debtor_ref || '') + '</td></tr>';
    html += '<tr><td>Phone</td><td>' + (d.phone || '') + '</td></tr>';
    html += '<tr><td>Email</td><td>' + (d.email || '') + '</td></tr>';
    html += '<tr><td>ID</td><td>' + (d.debtor_no || '') + '</td></tr>';
  } else {
    html += '<tr><td>Type</td><td>Person</td></tr>';
    html += '<tr><td>Ref</td><td>' + (d.ref || '') + '</td></tr>';
    html += '<tr><td>Phone</td><td>' + (d.phone || '') + '</td></tr>';
    html += '<tr><td>Email</td><td>' + (d.email || '') + '</td></tr>';
  }

  if (tagsHtml) {
    html += '<tr><td>Tags</td><td>' + tagsHtml + '</td></tr>';
  }

  var connected = [];
  edges.forEach(function (e) {
    if (e.from === nodeId || e.to === nodeId) {
      var otherId = e.from === nodeId ? e.to : e.from;
      var other = nodeData[otherId];
      if (other) {
        connected.push({ id: otherId, label: other.label, rel: e.label || '' });
      }
    }
  });

  if (connected.length) {
    html += '<tr><td colspan="2"><strong>Connections</strong></td></tr>';
    connected.forEach(function (c) {
      html += '<tr><td style="padding-left:12px" colspan="2">&mdash; ' + c.label + ' <em>(' + c.rel + ')</em></td></tr>';
    });
  }

  html += '</table>';
  panel.innerHTML = html;
  panel.style.display = 'block';
}

network.on('click', function (params) {
  if (params.nodes.length > 0) {
    showDetail(params.nodes[0]);
  }
});

network.on('oncontext', function (params) {
  params.event.preventDefault();
  var nodeId = network.getNodeAt(params.pointer.DOM);
  if (nodeId) {
    showDetail(nodeId);
  }
});

setTimeout(applyFilters, 100);
</script>

<?php
end_page();

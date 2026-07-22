<?php
/**
 * All Tools — full directory with client-side filtering by category & query.
 * @package OmniTools
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$cats = omnitools_categories();
$allTools = omnitools_tools();
$q = trim((string)($_GET['q'] ?? ''));

$page = [
    'title'       => 'All Tools, ' . tools_count() . '+ Free Online Tools | ' . SITE_NAME,
    'description' => 'Browse all ' . tools_count() . '+ free online tools on ' . SITE_NAME . '. Filter by category or search instantly.',
    'canonical'   => url('tools'),
    'breadcrumb'  => [
        ['name' => 'Home', 'url' => url()],
        ['name' => 'All Tools', 'url' => url('tools')],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
  <div class="container">
    <h1>All Tools</h1>
    <p><?= tools_count() ?>+ free tools across <?= count($cats) ?> categories, filter or search to find exactly what you need.</p>
  </div>
</section>

<div class="container">
  <div class="field" style="max-width:520px;margin:0 auto 22px">
    <input id="toolFilter" class="input" type="search" placeholder="Filter tools…" value="<?= eattr($q) ?>" aria-label="Filter tools">
  </div>

  <div class="chips" id="catFilters" style="justify-content:center;margin-bottom:28px">
    <button class="chip is-active" data-cat="all">All</button>
    <?php foreach ($cats as $slug => $c): ?>
      <button class="chip" data-cat="<?= eattr($slug) ?>"><?= e($c['name']) ?></button>
    <?php endforeach; ?>
  </div>
</div>

<section class="section--tight container">
  <div class="cards" id="toolGrid">
    <?php foreach ($allTools as $tool): ?>
      <div class="tool-item" data-cat="<?= eattr($tool['category']) ?>"
           data-name="<?= eattr(strtolower($tool['name'] . ' ' . $tool['desc'] . ' ' . $tool['keywords'])) ?>">
        <?= render_tool_card($tool) ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div id="noResults" class="text-center muted hidden" style="padding:40px">No tools match your filter.</div>
</section>

<script>
(function () {
  var filter = document.getElementById('toolFilter');
  var items = Array.from(document.querySelectorAll('.tool-item'));
  var chips = Array.from(document.querySelectorAll('#catFilters .chip'));
  var noRes = document.getElementById('noResults');
  var activeCat = 'all';

  function apply() {
    var term = filter.value.trim().toLowerCase();
    var shown = 0;
    items.forEach(function (it) {
      var okCat = activeCat === 'all' || it.dataset.cat === activeCat;
      var okTerm = !term || it.dataset.name.indexOf(term) !== -1;
      var vis = okCat && okTerm;
      it.style.display = vis ? '' : 'none';
      if (vis) shown++;
    });
    noRes.classList.toggle('hidden', shown > 0);
  }
  filter.addEventListener('input', apply);
  chips.forEach(function (c) {
    c.addEventListener('click', function () {
      chips.forEach(function (x) { x.classList.remove('is-active'); });
      c.classList.add('is-active');
      activeCat = c.dataset.cat;
      apply();
    });
  });
  if (filter.value) apply();
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

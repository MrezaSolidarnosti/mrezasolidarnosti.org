<?php
/**
 * The transaction list itself, iframed and filtered to this donor — so the tab has the same
 * actions (confirm, cancel, bulk), sorting, filters and export as /transaction/view/, with no
 * second copy of any of it.
 *
 * An iframe rather than a DataTable in the tab because skeletorjs binds its DataTable to fixed
 * element ids (#mainTable, #crudTable, ...), one per document — and the donor list under this
 * modal already owns them. The iframe is its own document, so nothing collides.
 *
 * Transaction.js reads ?donor= and applies it as custom filter data before the first load;
 * ?embedded=1 makes crudTableLayout drop the navigation.
 */
?>
<div id="donorInstructions">
    <iframe src="/transaction/view/?donor=<?= (int) $donorId ?>&embedded=1"
            title="Instrukcije donatora"
            loading="lazy"></iframe>
</div>

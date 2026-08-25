<?php
//$this->layout('layout::standard', ['pageTitle' => 'Statistika']);
$this->layout('layout::crudTableLayout', ['pageTitle' => 'Statistika', 'data' => $data]);

$formatNumber = function(int $amount): string {
    return number_format($amount, 0, ',', '.');
};
?>

<style>
    .statsPage { padding: 1rem; }
    .statsPage .tabs {
        display: flex;
        gap: 0;
        border-bottom: 2px solid var(--colorOnBorders, #ddd);
        margin-bottom: 1.5rem;
    }
    .statsPage .tabs .tab {
        padding: 0.75rem 1.5rem;
        cursor: pointer;
        border-bottom: 3px solid transparent;
        font-weight: 600;
        color: var(--colorOnSurface-400, #666);
        transition: 0.2s ease all;
        user-select: none;
    }
    .statsPage .tabs .tab:hover {
        color: var(--colorOnSurface-600, #333);
    }
    .statsPage .tabs .tab.active {
        color: #fff;
        background: var(--colorPrimary-500, #6c5ce7);
        border-radius: 6px 6px 0 0;
        border-bottom-color: var(--colorPrimary-500, #6c5ce7);
    }
    .statsPage .tabContent { display: none; }
    .statsPage .tabContent.active { display: block; }
    .statsGrid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .statCard {
        background: var(--colorSurface-100, #fff);
        border: 1px solid var(--colorOnBorders, #e0e0e0);
        border-radius: 8px;
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .statCard .statLabel {
        font-size: 0.85rem;
        color: var(--colorOnSurface-400, #888);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .statCard .statValue {
        font-size: 1.5rem;
        font-weight: 700;
        color: #fff;
    }
    .statCard .statValue .statUnit {
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--colorOnSurface-400, #888);
    }
    .statsSection {
        margin-bottom: 1rem;
    }
    .statsSection h3 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--colorOnSurface-500, #555);
        margin-bottom: 0.75rem;
        padding-bottom: 0.25rem;
        border-bottom: 1px solid var(--colorOnBorders, #eee);
    }
    .subTabs {
        display: flex;
        gap: 0;
        flex-wrap: wrap;
        margin-bottom: 1rem;
        border-bottom: 1px solid var(--colorOnBorders, #ddd);
    }
    .subTabs .subTab {
        padding: 0.5rem 1rem;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--colorOnSurface-400, #888);
        border-bottom: 2px solid transparent;
        transition: 0.2s ease all;
        user-select: none;
    }
    .subTabs .subTab:hover {
        color: var(--colorOnSurface-600, #ccc);
    }
    .subTabs .subTab.active {
        color: var(--colorPrimary-400, #a587fa);
        border-bottom-color: var(--colorPrimary-400, #a587fa);
    }
    .subTabContent { display: none; }
    .subTabContent.active { display: block; }
</style>

<div class="statsPage">
    <h2 style="margin-bottom: 1rem;">Statistika</h2>

    <div class="tabs">
        <div class="tab active" data-tab="global">Ukupno</div>
        <?php foreach ($data['projects'] as $project): ?>
            <div class="tab" data-tab="project-<?= $project->id ?>"><?= htmlspecialchars($project->code) ?></div>
        <?php endforeach; ?>
    </div>

    <!-- Global tab -->
    <div class="tabContent active" data-tab="global">
        <?php $s = $data['globalStats']; ?>
        <div class="statsSection">
            <h3>Pregled</h3>
            <div class="statsGrid">
                <div class="statCard">
                    <span class="statLabel">Broj donatora</span>
                    <span class="statValue"><?= $formatNumber($s['donorCount']) ?></span>
                </div>
                <div class="statCard">
                    <span class="statLabel">Mesecni donatori</span>
                    <span class="statValue"><?= $formatNumber($s['monthlyDonorCount']) ?></span>
                </div>
                <div class="statCard">
                    <span class="statLabel">Broj ostecenih</span>
                    <span class="statValue"><?= $formatNumber($s['beneficiaryCount']) ?></span>
                </div>
                <div class="statCard">
                    <span class="statLabel">Broj delegata</span>
                    <span class="statValue"><?= $formatNumber($s['delegateCount']) ?></span>
                </div>
            </div>
        </div>
        <div class="statsSection">
            <h3>Donacije</h3>
            <div class="statsGrid">
                <div class="statCard">
                    <span class="statLabel">Ukupno obecano</span>
                    <span class="statValue"><?= $formatNumber($s['totalPledged']) ?> <span class="statUnit">RSD</span></span>
                </div>
                <div class="statCard">
                    <span class="statLabel">Mesecne donacije</span>
                    <span class="statValue"><?= $formatNumber($s['monthlyPledged']) ?> <span class="statUnit">RSD</span></span>
                </div>
            </div>
        </div>
        <div class="statsSection">
            <h3>Transakcije</h3>
            <div class="statsGrid">
                <?php // Potvrdene + placene, the same pair the front page totals. This is the
                      // only figure carrying the historical adjustment; the two cards after it
                      // are pure database sums. ?>
                <div class="statCard">
                    <span class="statLabel">Ukupno prikupljeno (<?= $formatNumber($s['realizedCount']) ?>)</span>
                    <span class="statValue"><?= $formatNumber($s['realizedAmount']) ?> <span class="statUnit">RSD</span></span>
                    <?php if (!empty($s['historicalAdjustment'])): ?>
                        <?php // Backend only. The count is the real row count and is NOT adjusted,
                              // so the two deliberately do not reconcile — this line says why. ?>
                        <span class="statNote" style="display:block;margin-top:6px;font-size:12px;color:#fff;">
                            Ukljucuje <?= $formatNumber($s['historicalAdjustment']) ?> RSD istorijske korekcije
                            (nije sadrzano u broju transakcija).<br>
                            <?= htmlspecialchars($s['historicalAdjustmentNote'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="statCard">
                    <span class="statLabel">Potvrdene donacije (<?= $formatNumber($s['confirmedCount']) ?>)</span>
                    <span class="statValue"><?= $formatNumber($s['confirmedAmount']) ?> <span class="statUnit">RSD</span></span>
                </div>
                <div class="statCard">
                    <span class="statLabel">Placene donacije (<?= $formatNumber($s['paidCount']) ?>)</span>
                    <span class="statValue"><?= $formatNumber($s['paidAmount']) ?> <span class="statUnit">RSD</span></span>
                </div>
                <div class="statCard">
                    <span class="statLabel">Aktivne instrukcije (<?= $formatNumber($s['activeCount']) ?>)</span>
                    <span class="statValue"><?= $formatNumber($s['activeAmount']) ?> <span class="statUnit">RSD</span></span>
                </div>
                <div class="statCard">
                    <span class="statLabel">Otkazane (<?= $formatNumber($s['cancelledCount']) ?>)</span>
                    <span class="statValue"><?= $formatNumber($s['cancelledAmount']) ?> <span class="statUnit">RSD</span></span>
                </div>
                <div class="statCard">
                    <span class="statLabel">Istekle (<?= $formatNumber($s['expiredCount']) ?>)</span>
                    <span class="statValue"><?= $formatNumber($s['expiredAmount']) ?> <span class="statUnit">RSD</span></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Per-project tabs -->
    <?php foreach ($data['projectStats'] as $projectId => $ps): ?>
        <div class="tabContent" data-tab="project-<?= $projectId ?>">
            <?php $s = $ps['stats']; ?>
            <div class="statsSection">
                <h3>Pregled — <?= htmlspecialchars($ps['project']->code . ' - ' . $ps['project']->name) ?></h3>
                <div class="statsGrid">
                    <div class="statCard">
                        <span class="statLabel">Broj donatora</span>
                        <span class="statValue"><?= $formatNumber($s['donorCount']) ?></span>
                    </div>
                    <div class="statCard">
                        <span class="statLabel">Mesecni donatori</span>
                        <span class="statValue"><?= $formatNumber($s['monthlyDonorCount']) ?></span>
                    </div>
                    <div class="statCard">
                        <span class="statLabel">Broj ostecenih</span>
                        <span class="statValue"><?= $formatNumber($s['beneficiaryCount']) ?></span>
                    </div>
                    <div class="statCard">
                        <span class="statLabel">Broj delegata</span>
                        <span class="statValue"><?= $formatNumber($s['delegateCount']) ?></span>
                    </div>
                </div>
            </div>
            <div class="statsSection">
                <h3>Donacije</h3>
                <div class="statsGrid">
                    <div class="statCard">
                        <span class="statLabel">Ukupno obecano</span>
                        <span class="statValue"><?= $formatNumber($s['totalPledged']) ?> <span class="statUnit">RSD</span></span>
                    </div>
                    <div class="statCard">
                        <span class="statLabel">Mesecne donacije</span>
                        <span class="statValue"><?= $formatNumber($s['monthlyPledged']) ?> <span class="statUnit">RSD</span></span>
                    </div>
                </div>
            </div>
            <div class="statsSection">
                <h3>Transakcije (ukupno)</h3>
                <div class="statsGrid">
                    <?php // Project-scoped: getStats($project) resolves the adjustment by
                          // project code, so a project without one shows a plain sum. ?>
                    <div class="statCard">
                        <span class="statLabel">Ukupno prikupljeno (<?= $formatNumber($s['realizedCount']) ?>)</span>
                        <span class="statValue"><?= $formatNumber($s['realizedAmount']) ?> <span class="statUnit">RSD</span></span>
                        <?php if (!empty($s['historicalAdjustment'])): ?>
                            <span class="statNote" style="display:block;margin-top:6px;font-size:12px;color:#fff;">
                                Ukljucuje <?= $formatNumber($s['historicalAdjustment']) ?> RSD istorijske korekcije
                                (nije sadrzano u broju transakcija).
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="statCard">
                        <span class="statLabel">Potvrdene donacije (<?= $formatNumber($s['confirmedCount']) ?>)</span>
                        <span class="statValue"><?= $formatNumber($s['confirmedAmount']) ?> <span class="statUnit">RSD</span></span>
                    </div>
                    <div class="statCard">
                        <span class="statLabel">Placene donacije (<?= $formatNumber($s['paidCount']) ?>)</span>
                        <span class="statValue"><?= $formatNumber($s['paidAmount']) ?> <span class="statUnit">RSD</span></span>
                    </div>
                    <div class="statCard">
                        <span class="statLabel">Aktivne instrukcije (<?= $formatNumber($s['activeCount']) ?>)</span>
                        <span class="statValue"><?= $formatNumber($s['activeAmount']) ?> <span class="statUnit">RSD</span></span>
                    </div>
                    <div class="statCard">
                        <span class="statLabel">Otkazane (<?= $formatNumber($s['cancelledCount']) ?>)</span>
                        <span class="statValue"><?= $formatNumber($s['cancelledAmount']) ?> <span class="statUnit">RSD</span></span>
                    </div>
                    <div class="statCard">
                        <span class="statLabel">Istekle (<?= $formatNumber($s['expiredCount']) ?>)</span>
                        <span class="statValue"><?= $formatNumber($s['expiredAmount']) ?> <span class="statUnit">RSD</span></span>
                    </div>
                </div>
            </div>

            <?php if (!empty($ps['periods'])): ?>
            <div class="statsSection">
                <h3>Po periodima</h3>
                <div class="subTabs">
                    <?php foreach ($ps['periods'] as $i => $period): ?>
                        <div class="subTab <?= $i === 0 ? 'active' : '' ?>" data-subtab="period-<?= $projectId ?>-<?= $period->getId() ?>"><?= htmlspecialchars($period->getLabel()) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($ps['periodStats'] as $periodId => $pst): ?>
                    <?php $ps2 = $pst['stats']; $isFirst = array_key_first($ps['periodStats']) === $periodId; ?>
                    <div class="subTabContent <?= $isFirst ? 'active' : '' ?>" data-subtab="period-<?= $projectId ?>-<?= $periodId ?>">
                        <div class="statsGrid">
                            <div class="statCard">
                                <span class="statLabel">Ostecenih u periodu</span>
                                <span class="statValue"><?= $formatNumber($ps2['beneficiaryCount']) ?></span>
                            </div>
                            <div class="statCard">
                                <span class="statLabel">Potvrdene (<?= $formatNumber($ps2['confirmedCount']) ?>)</span>
                                <span class="statValue"><?= $formatNumber($ps2['confirmedAmount']) ?> <span class="statUnit">RSD</span></span>
                            </div>
                            <div class="statCard">
                                <span class="statLabel">Placene (<?= $formatNumber($ps2['paidCount']) ?>)</span>
                                <span class="statValue"><?= $formatNumber($ps2['paidAmount']) ?> <span class="statUnit">RSD</span></span>
                            </div>
                            <div class="statCard">
                                <span class="statLabel">Aktivne (<?= $formatNumber($ps2['activeCount']) ?>)</span>
                                <span class="statValue"><?= $formatNumber($ps2['activeAmount']) ?> <span class="statUnit">RSD</span></span>
                            </div>
                            <div class="statCard">
                                <span class="statLabel">Otkazane (<?= $formatNumber($ps2['cancelledCount']) ?>)</span>
                                <span class="statValue"><?= $formatNumber($ps2['cancelledAmount']) ?> <span class="statUnit">RSD</span></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

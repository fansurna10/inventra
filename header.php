
<header class="topbar">

    <div class="page-title">
        <h1><?= htmlspecialchars($pageTitle ?? 'Dashboard Overview') ?></h1>
        <p><?= htmlspecialchars($pageDescription ?? 'Ringkasan informasi persediaan perusahaan.') ?></p>
    </div>

    <div class="topbar-actions">

        <div class="date-box">
            <span class="date-icon">▦</span>

            <div>
                <strong><?= htmlspecialchars($namaHari ?? '') ?></strong>
                <span><?= htmlspecialchars($tanggal ?? date('d-m-Y')) ?></span>
            </div>
        </div>

        <div class="notification">
            <span>♧</span>
        </div>

        <div class="top-admin">
            <div class="top-avatar">A</div>
            <div>
                <strong><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Administrator') ?></strong>
                <span><?= htmlspecialchars($_SESSION['role'] ?? 'Admin') ?></span>
            </div>
        </div>

    </div>

</header>

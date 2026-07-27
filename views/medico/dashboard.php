<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/CitaController.php';
require_once __DIR__ . '/../../controllers/MedicoController.php';

requerirRol('medico');

$citaCtrl = new CitaController();
$medicoCtrl = new MedicoController();

$idMedico = (int) $_SESSION['id_medico'];
$misCitas = $citaCtrl->listarPorMedico($idMedico);
$misPacientes = $medicoCtrl->pacientesPorMedico($idMedico);

$estadoLabels = [
    'pendiente' => 'Pendiente', 'confirmada' => 'Confirmada',
    'cancelada' => 'Cancelada', 'completada' => 'Completada',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel médico · Consultorio Médico</title>
<link rel="stylesheet" href="../../public/css/futuristic.css">
</head>
<body>

<header class="scan-header">
    <div class="brand"><span class="dot" style="background:var(--accent-warning, #FFB454);box-shadow:0 0 12px 2px #FFB454;"></span> ConsultorioMed · Médico</div>
    <nav class="nav-links">
        <a href="#citas" class="active">Mis citas</a>
        <a href="#pacientes">Mis pacientes</a>
        <button id="btn-logout" class="btn-ghost-logout">Cerrar sesión</button>
    </nav>
</header>

<main class="page-wrap">
    <span class="eyebrow">Panel médico</span>
    <h1 class="page-title">Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?></h1>
    <p class="page-sub">Gestione su agenda y el historial clínico de sus pacientes (diagnósticos, recetas y referencias).</p>

    <div class="stat-strip">
        <div class="stat-chip"><div class="num"><?= count($misCitas) ?></div><div class="label">Citas totales</div></div>
        <div class="stat-chip"><div class="num"><?= count(array_filter($misCitas, fn($c) => $c['estado'] === 'pendiente')) ?></div><div class="label">Por confirmar</div></div>
        <div class="stat-chip"><div class="num"><?= count(array_filter($misCitas, fn($c) => $c['estado'] === 'confirmada')) ?></div><div class="label">Confirmadas</div></div>
        <div class="stat-chip"><div class="num"><?= count($misPacientes) ?></div><div class="label">Pacientes atendidos</div></div>
    </div>

    <section id="citas" class="panel" style="margin-bottom:32px;">
        <h3 style="margin-bottom:18px;">Mi agenda</h3>
        <?php if (empty($misCitas)): ?>
            <div class="empty-state"><div class="icon">🗓️</div>No tiene citas registradas todavía.</div>
        <?php else: ?>
        <table>
            <thead><tr><th>Paciente</th><th>Fecha</th><th>Hora</th><th>Motivo</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($misCitas as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['paciente']) ?></td>
                    <td><?= htmlspecialchars($c['fecha_cita']) ?></td>
                    <td><?= substr($c['hora_inicio'], 0, 5) ?> - <?= substr($c['hora_fin'], 0, 5) ?></td>
                    <td style="color:var(--text-muted);font-size:0.85rem;"><?= htmlspecialchars($c['motivo'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= $c['estado'] ?>"><?= $estadoLabels[$c['estado']] ?></span></td>
                    <td class="row-actions">
                        <?php if ($c['estado'] === 'pendiente'): ?>
                            <button class="btn btn-sm" data-cambiar-estado="confirmada" data-id-cita="<?= $c['id_cita'] ?>">Confirmar</button>
                        <?php endif; ?>
                        <?php if (in_array($c['estado'], ['pendiente', 'confirmada'])): ?>
                            <button class="btn btn-danger btn-sm" data-cambiar-estado="cancelada" data-id-cita="<?= $c['id_cita'] ?>">Cancelar</button>
                        <?php endif; ?>
                        <?php if ($c['estado'] === 'confirmada'): ?>
                            <button class="btn btn-secondary btn-sm" data-cambiar-estado="completada" data-id-cita="<?= $c['id_cita'] ?>">Completar</button>
                        <?php endif; ?>
                        <button class="btn btn-secondary btn-sm" data-historial-paciente="<?= $c['id_paciente'] ?>" data-nombre="<?= htmlspecialchars($c['paciente'], ENT_QUOTES) ?>">Historial</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <section id="pacientes" class="panel">
        <h3 style="margin-bottom:18px;">Mis pacientes</h3>
        <?php if (empty($misPacientes)): ?>
            <div class="empty-state"><div class="icon">🩺</div>Aún no ha atendido pacientes.</div>
        <?php else: ?>
        <table>
            <thead><tr><th>Nombre</th><th>Teléfono</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($misPacientes as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['nombre']) ?></td>
                    <td style="font-family:var(--font-mono);font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($p['telefono'] ?? '—') ?></td>
                    <td class="row-actions">
                        <button class="btn btn-sm" data-historial-paciente="<?= $p['id_paciente'] ?>" data-nombre="<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>">Ver historial</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>
</main>

<!-- Modal genérico reutilizado por las acciones del panel médico -->
<div id="modal-overlay">
    <div class="modal-box">
        <button id="modal-close" aria-label="Cerrar">&times;</button>
        <div id="modal-box-content"></div>
    </div>
</div>

<?php include __DIR__ . '/../partials/toast-container.php'; ?>
<script>
    window.ROL_ACTUAL = 'medico';
</script>
<script src="../../public/js/app.js"></script>
</body>
</html>

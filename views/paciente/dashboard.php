<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/CitaController.php';
require_once __DIR__ . '/../../controllers/MedicoController.php';

requerirRol('paciente');

$citaCtrl = new CitaController();
$medicoCtrl = new MedicoController();

$medicos = $medicoCtrl->listar();
$misCitas = $citaCtrl->listarPorPaciente((int) $_SESSION['id_paciente']);

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
<title>Mi panel · Consultorio Médico</title>
<link rel="stylesheet" href="../../public/css/futuristic.css">
</head>
<body>

<header class="scan-header">
    <div class="brand"><span class="dot"></span> ConsultorioMed</div>
    <nav class="nav-links">
        <a href="#agendar" class="active">Agendar cita</a>
        <a href="#mis-citas">Mis citas</a>
        <button id="btn-logout" class="btn-ghost-logout">Cerrar sesión</button>
    </nav>
</header>

<main class="page-wrap">
    <span class="eyebrow">Panel del paciente</span>
    <h1 class="page-title">Hola, <?= htmlspecialchars($_SESSION['nombre']) ?></h1>
    <p class="page-sub">Agende una cita seleccionando médico, fecha y un bloque disponible. La disponibilidad se valida en tiempo real contra la agenda del médico.</p>

    <div class="stat-strip">
        <div class="stat-chip"><div class="num"><?= count($misCitas) ?></div><div class="label">Citas totales</div></div>
        <div class="stat-chip"><div class="num"><?= count(array_filter($misCitas, fn($c) => $c['estado'] === 'pendiente')) ?></div><div class="label">Pendientes</div></div>
        <div class="stat-chip"><div class="num"><?= count(array_filter($misCitas, fn($c) => $c['estado'] === 'confirmada')) ?></div><div class="label">Confirmadas</div></div>
    </div>

    <button id="btn-mi-historial" class="btn btn-secondary btn-sm" style="margin-bottom:24px;" data-historial-paciente="<?= (int) $_SESSION['id_paciente'] ?>" data-nombre="<?= htmlspecialchars($_SESSION['nombre'], ENT_QUOTES) ?>">Ver mi historial médico</button>

    <section id="agendar" class="panel" style="margin-bottom:32px;">
        <h3 style="margin-bottom:18px;">Agendar nueva cita</h3>

        <div class="grid-2">
            <div>
                <label>1. Seleccione un médico</label>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach ($medicos as $m): ?>
                        <div class="card-medico" data-id-medico="<?= $m['id_medico'] ?>">
                            <h4><?= htmlspecialchars($m['nombre']) ?></h4>
                            <span class="especialidad"><?= htmlspecialchars($m['especialidad']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <label for="input-fecha">2. Seleccione una fecha</label>
                <input type="date" id="input-fecha">

                <div id="osciloscopio-wrapper" style="display:none;">
                    <label style="margin-top:20px;">3. Elija un horario disponible</label>
                    <div id="osciloscopio" class="oscilloscope"></div>
                    <div class="osc-legend">
                        <span class="legend-libre"><i></i> Disponible</span>
                        <span class="legend-ocupado"><i></i> Ocupado</span>
                    </div>
                    <p id="resumen-slot" style="font-family:var(--font-mono);font-size:0.8rem;color:var(--accent-cyan);margin-top:10px;"></p>
                </div>

                <form id="form-cita">
                    <label for="input-motivo">Motivo de la consulta (opcional)</label>
                    <textarea id="input-motivo" rows="2" placeholder="Ej. Chequeo general, dolor persistente..."></textarea>
                    <button type="submit" id="btn-confirmar-cita" class="btn btn-full" style="margin-top:16px;" disabled>Confirmar cita</button>
                </form>
            </div>
        </div>
    </section>

    <section id="mis-citas" class="panel">
        <h3 style="margin-bottom:18px;">Mis citas</h3>
        <?php if (empty($misCitas)): ?>
            <div class="empty-state"><div class="icon">🩺</div>Aún no tiene citas registradas.</div>
        <?php else: ?>
        <table>
            <thead><tr><th>Fecha</th><th>Hora</th><th>Médico</th><th>Especialidad</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($misCitas as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['fecha_cita']) ?></td>
                    <td><?= substr($c['hora_inicio'], 0, 5) ?> - <?= substr($c['hora_fin'], 0, 5) ?></td>
                    <td><?= htmlspecialchars($c['medico']) ?></td>
                    <td><?= htmlspecialchars($c['especialidad']) ?></td>
                    <td><span class="badge badge-<?= $c['estado'] ?>"><?= $estadoLabels[$c['estado']] ?></span></td>
                    <td>
                        <?php if (in_array($c['estado'], ['pendiente', 'confirmada'])): ?>
                            <button class="btn btn-danger btn-sm" data-cambiar-estado="cancelada" data-id-cita="<?= $c['id_cita'] ?>">Cancelar</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>
</main>

<!-- Modal genérico (usado para ver el historial médico) -->
<div id="modal-overlay">
    <div class="modal-box">
        <button id="modal-close" aria-label="Cerrar">&times;</button>
        <div id="modal-box-content"></div>
    </div>
</div>

<?php include __DIR__ . '/../partials/toast-container.php'; ?>
<script>
    window.ROL_ACTUAL = 'paciente';
</script>
<script src="../../public/js/app.js"></script>
</body>
</html>

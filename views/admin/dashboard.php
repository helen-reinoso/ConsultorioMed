<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/CitaController.php';
require_once __DIR__ . '/../../controllers/MedicoController.php';
require_once __DIR__ . '/../../controllers/PacienteController.php';

requerirRol('admin');

$citaCtrl = new CitaController();
$medicoCtrl = new MedicoController();
$pacienteCtrl = new PacienteController();

$todasCitas = $citaCtrl->listarTodas();
$medicos = $medicoCtrl->listar();
$pacientes = $pacienteCtrl->listar();
$especialidades = $medicoCtrl->listarEspecialidades();

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
<title>Panel administrativo · Consultorio Médico</title>
<link rel="stylesheet" href="../../public/css/futuristic.css">
</head>
<body>

<header class="scan-header">
    <div class="brand"><span class="dot" style="background:var(--accent-violet);box-shadow:0 0 12px 2px var(--accent-violet);"></span> ConsultorioMed · Admin</div>
    <nav class="nav-links">
        <a href="#citas" class="active">Citas</a>
        <a href="#medicos">Médicos</a>
        <a href="#especialidades">Especialidades</a>
        <a href="#pacientes">Pacientes</a>
        <button id="btn-logout" class="btn-ghost-logout">Cerrar sesión</button>
    </nav>
</header>

<main class="page-wrap">
    <span class="eyebrow">Panel administrativo</span>
    <h1 class="page-title">Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?></h1>
    <p class="page-sub">Gestione citas, médicos, horarios, especialidades y pacientes del consultorio.</p>

    <div class="stat-strip">
        <div class="stat-chip"><div class="num"><?= count($todasCitas) ?></div><div class="label">Citas totales</div></div>
        <div class="stat-chip"><div class="num"><?= count(array_filter($todasCitas, fn($c) => $c['estado'] === 'pendiente')) ?></div><div class="label">Por confirmar</div></div>
        <div class="stat-chip"><div class="num"><?= count($medicos) ?></div><div class="label">Médicos activos</div></div>
        <div class="stat-chip"><div class="num"><?= count($pacientes) ?></div><div class="label">Pacientes registrados</div></div>
    </div>

    <section id="citas" class="panel" style="margin-bottom:32px;">
        <div class="section-header">
            <h3>Gestión de citas</h3>
            <button id="btn-nueva-cita" class="btn btn-sm">+ Nueva cita</button>
        </div>
        <?php if (empty($todasCitas)): ?>
            <div class="empty-state"><div class="icon">📋</div>No hay citas registradas todavía.</div>
        <?php else: ?>
        <table>
            <thead><tr><th>Paciente</th><th>Médico</th><th>Fecha</th><th>Hora</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($todasCitas as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['paciente']) ?></td>
                    <td><?= htmlspecialchars($c['medico']) ?> <br><small style="color:var(--text-muted);"><?= htmlspecialchars($c['especialidad']) ?></small></td>
                    <td><?= htmlspecialchars($c['fecha_cita']) ?></td>
                    <td><?= substr($c['hora_inicio'], 0, 5) ?> - <?= substr($c['hora_fin'], 0, 5) ?></td>
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
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <section id="medicos" class="panel" style="margin-bottom:32px;">
        <div class="section-header">
            <h3>Médicos registrados</h3>
            <button id="btn-nuevo-medico" class="btn btn-sm">+ Nuevo médico</button>
        </div>
        <?php if (empty($medicos)): ?>
            <div class="empty-state"><div class="icon">🩺</div>No hay médicos registrados.</div>
        <?php else: ?>
        <table>
            <thead><tr><th>Nombre</th><th>Especialidad</th><th>Contacto</th><th>Acceso</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($medicos as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['nombre']) ?></td>
                    <td><?= htmlspecialchars($m['especialidad']) ?></td>
                    <td style="font-family:var(--font-mono);font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($m['email'] ?? '—') ?></td>
                    <td>
                        <?php if (!empty($m['id_usuario'])): ?>
                            <span class="badge badge-confirmada">Con acceso</span>
                        <?php else: ?>
                            <span class="badge badge-pendiente">Sin acceso</span>
                        <?php endif; ?>
                    </td>
                    <td class="row-actions">
                        <button class="btn btn-secondary btn-sm" data-editar-medico="<?= $m['id_medico'] ?>">Editar</button>
                        <button class="btn btn-secondary btn-sm" data-horarios-medico="<?= $m['id_medico'] ?>" data-nombre="<?= htmlspecialchars($m['nombre'], ENT_QUOTES) ?>">Horarios</button>
                        <?php if (empty($m['id_usuario'])): ?>
                            <button class="btn btn-secondary btn-sm" data-crear-acceso-medico="<?= $m['id_medico'] ?>" data-nombre="<?= htmlspecialchars($m['nombre'], ENT_QUOTES) ?>">Crear acceso</button>
                        <?php endif; ?>
                        <button class="btn btn-danger btn-sm" data-eliminar-medico="<?= $m['id_medico'] ?>" data-nombre="<?= htmlspecialchars($m['nombre'], ENT_QUOTES) ?>">Desactivar</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <div class="grid-2">
        <section id="especialidades" class="panel">
            <div class="section-header">
                <h3>Especialidades</h3>
                <button id="btn-nueva-especialidad" class="btn btn-sm">+ Nueva</button>
            </div>
            <table>
                <thead><tr><th>Nombre</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($especialidades as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars($e['nombre']) ?></td>
                        <td class="row-actions">
                            <button class="btn btn-danger btn-sm" data-eliminar-especialidad="<?= $e['id_especialidad'] ?>" data-nombre="<?= htmlspecialchars($e['nombre'], ENT_QUOTES) ?>">Eliminar</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section id="pacientes" class="panel">
            <div class="section-header">
                <h3>Pacientes registrados</h3>
                <button id="btn-nuevo-paciente" class="btn btn-sm">+ Nuevo paciente</button>
            </div>
            <table>
                <thead><tr><th>Nombre</th><th>Teléfono</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($pacientes as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['nombre']) ?></td>
                        <td style="font-family:var(--font-mono);font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($p['telefono'] ?? '—') ?></td>
                        <td class="row-actions">
                            <button class="btn btn-secondary btn-sm" data-editar-paciente="<?= $p['id_paciente'] ?>">Editar</button>
                            <button class="btn btn-secondary btn-sm" data-historial-paciente="<?= $p['id_paciente'] ?>" data-nombre="<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>">Historial</button>
                            <button class="btn btn-danger btn-sm" data-eliminar-paciente="<?= $p['id_paciente'] ?>" data-nombre="<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>">Eliminar</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </div>
</main>

<!-- Modal genérico reutilizado por todas las acciones CRUD -->
<div id="modal-overlay">
    <div class="modal-box">
        <button id="modal-close" aria-label="Cerrar">&times;</button>
        <div id="modal-box-content"></div>
    </div>
</div>

<?php include __DIR__ . '/../partials/toast-container.php'; ?>
<script>
    // Datos embebidos para que app.js pueda prellenar los formularios de edición
    // sin hacer una llamada adicional a la API.
    window.ROL_ACTUAL = 'admin';
    window.ESPECIALIDADES = <?= json_encode($especialidades, JSON_UNESCAPED_UNICODE) ?>;
    window.MEDICOS = <?= json_encode($medicos, JSON_UNESCAPED_UNICODE) ?>;
    window.PACIENTES = <?= json_encode($pacientes, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="../../public/js/app.js"></script>
</body>
</html>

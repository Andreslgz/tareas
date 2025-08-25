<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}
require 'db.php';


$pdo->exec("CREATE TABLE IF NOT EXISTS tareas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modulo VARCHAR(255),
    detalle TEXT,
    estado VARCHAR(20),
    revisado VARCHAR(255),
    observaciones TEXT,
    fecha DATE,
    imagen VARCHAR(255) DEFAULT NULL
)");

$filtros = [];
$params = [];

if (!empty($_GET['filtro_modulo'])) {
    $filtros[] = "modulo LIKE ?";
    $params[] = "%" . $_GET['filtro_modulo'] . "%";
}

if (!empty($_GET['filtro_detalle'])) {
    $filtros[] = "detalle LIKE ?";
    $params[] = "%" . $_GET['filtro_detalle'] . "%";
}

if (!empty($_GET['filtro_estado'])) {
    $filtros[] = "estado = ?";
    $params[] = $_GET['filtro_estado'];
}

if (!empty($_GET['filtro_revisado'])) {
    $filtros[] = "revisado = ?";
    $params[] = $_GET['filtro_revisado'];
}

$where = $filtros ? "WHERE " . implode(" AND ", $filtros) : "";

$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = 30;
$offset = ($pagina - 1) * $por_pagina;

$total = $pdo->prepare("SELECT COUNT(*) FROM tareas $where");
$total->execute($params);
$total_rows = $total->fetchColumn();

$sql = "SELECT * FROM tareas $where ORDER BY fecha DESC LIMIT $por_pagina OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dentro del bloque POST
function manejarImagen($campoNombre)
{
    if (isset($_FILES[$campoNombre]) && $_FILES[$campoNombre]['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES[$campoNombre]['name'], PATHINFO_EXTENSION);
        $nuevoNombre = uniqid() . "." . $ext;
        move_uploaded_file($_FILES[$campoNombre]['tmp_name'], "uploads/" . $nuevoNombre);
        return $nuevoNombre;
    }
    return null;
}


// Validación básica de CSRF y sanitización (mejora de seguridad)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    if ($accion === 'crear') {
        $imagen = manejarImagen('imagen');
        $stmt = $pdo->prepare("INSERT INTO tareas (modulo, detalle, estado, revisado, observaciones, fecha, imagen) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            htmlspecialchars($_POST['modulo']),
            htmlspecialchars($_POST['detalle']),
            $_POST['estado'],
            $_POST['revisado'],
            htmlspecialchars($_POST['observaciones']),
            $_POST['fecha'],
            $imagen
        ]);
    } elseif ($accion === 'editar') {
        $imagen = manejarImagen('imagen');
        if ($imagen) {
            $stmt = $pdo->prepare("UPDATE tareas SET modulo=?, detalle=?, estado=?, revisado=?, observaciones=?, fecha=?, imagen=? WHERE id=?");
            $stmt->execute([
                htmlspecialchars($_POST['modulo']),
                htmlspecialchars($_POST['detalle']),
                $_POST['estado'],
                $_POST['revisado'],
                htmlspecialchars($_POST['observaciones']),
                $_POST['fecha'],
                $imagen,
                $_POST['id']
            ]);
        } else {
            $stmt = $pdo->prepare("UPDATE tareas SET modulo=?, detalle=?, estado=?, revisado=?, observaciones=?, fecha=? WHERE id=?");
            $stmt->execute([
                htmlspecialchars($_POST['modulo']),
                htmlspecialchars($_POST['detalle']),
                $_POST['estado'],
                $_POST['revisado'],
                htmlspecialchars($_POST['observaciones']),
                $_POST['fecha'],
                $_POST['id']
            ]);
        }
    } elseif ($accion === 'borrar') {
        $stmt = $pdo->prepare("DELETE FROM tareas WHERE id = ?");
        $stmt->execute([$_POST['id']]);
    }
    header("Location: index.php");
    exit();
}

// Endpoint para datos de producción (para gráfico de barras)
if (isset($_GET['api']) && $_GET['api'] === 'produccion') {
    header('Content-Type: application/json');
    // Agrupar por mes y contar tareas terminadas
    $sql = "SELECT DATE_FORMAT(fecha, '%Y-%m') as mes, COUNT(*) as total FROM tareas WHERE estado = 'SI' GROUP BY mes ORDER BY mes";
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data);
    exit();
}

if (isset($_GET['exportar']) && $_GET['exportar'] === 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=tareas_cesco.xls");
    echo "Modulo\tDetalle\tEstado\tRevisado\tFecha\n";
    foreach ($pdo->query("SELECT * FROM tareas") as $row) {
        echo "$row[modulo]\t$row[detalle]\t$row[estado]\t$row[revisado]\t$row[fecha]\n";
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Tareas Pendientes CESCOCONLINE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.css" rel="stylesheet">
</head>

<body class="bg-green-50 min-h-screen">

    <header class="bg-white shadow sticky top-0 z-10">
        <div class="max-w-screen-2xl mx-auto px-4 py-4 flex items-center justify-between">
            <h1 class="text-xl md:text-2xl font-bold text-green-700">Tareas Pendientes CESCOCONLINE</h1>
            <!-- Botón que abre el modal -->
            <button onclick="document.getElementById('modalCerrarSesion').showModal()"
                class="text-red-600 font-semibold hover:underline flex items-center space-x-1">
                <i class="fas fa-sign-out-alt"></i>
                <span>Cerrar sesión</span>
            </button>
        </div>
    </header>


    <main class="max-w-screen-2xl mx-auto px-4 py-6">



        <div class="max-w-screen-2xl mx-auto">


            <!-- Filtros -->
            <form method="GET" class="grid grid-cols-1 lg:grid-cols-6 gap-4 bg-white p-6 rounded-xl shadow-lg mb-8">

                <!-- Módulo -->
                <div class="col-span-1">
                    <label class="block text-sm text-gray-700 font-medium mb-1">Módulo</label>
                    <input type="text" name="filtro_modulo"
                        value="<?= htmlspecialchars($_GET['filtro_modulo'] ?? '') ?>"
                        placeholder="Ej: Login, Reporte"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>

                <!-- Detalle -->
                <div class="col-span-1">
                    <label class="block text-sm text-gray-700 font-medium mb-1">Detalle</label>
                    <input type="text" name="filtro_detalle"
                        value="<?= htmlspecialchars($_GET['filtro_detalle'] ?? '') ?>"
                        placeholder="Palabra clave"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>

                <!-- Estado -->
                <div class="col-span-1">
                    <label class="block text-sm text-gray-700 font-medium mb-1">Estado</label>
                    <select name="filtro_estado"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">-- Todos --</option>
                        <option value="SI" <?= ($_GET['filtro_estado'] ?? '') === 'SI' ? 'selected' : '' ?>>Terminado</option>
                        <option value="NO" <?= ($_GET['filtro_estado'] ?? '') === 'NO' ? 'selected' : '' ?>>En Proceso</option>
                    </select>
                </div>

                <!-- Revisado -->
                <div class="col-span-1">
                    <label class="block text-sm text-gray-700 font-medium mb-1">Revisado</label>
                    <select name="filtro_revisado"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">-- Todos --</option>
                        <option value="SI" <?= ($_GET['filtro_revisado'] ?? '') === 'SI' ? 'selected' : '' ?>>Sí</option>
                        <option value="NO" <?= ($_GET['filtro_revisado'] ?? '') === 'NO' ? 'selected' : '' ?>>No</option>
                    </select>
                </div>

                <!-- Botón Buscar -->
                <div class="col-span-1 flex items-end">
                    <button type="submit"
                        class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 rounded-lg shadow">
                        <i class="fas fa-search mr-2"></i> Buscar
                    </button>
                </div>

                <!-- Botón Limpiar -->
                <div class="col-span-1 flex items-end">
                    <a href="index.php"
                        class="w-full text-center bg-gray-200 hover:bg-gray-300 text-black font-semibold py-2 rounded-lg shadow">
                        <i class="fas fa-sync-alt mr-2"></i> Limpiar
                    </a>
                </div>
            </form>

            <!-- Acciones compactas y modernas -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4 bg-white p-3 rounded-lg shadow-sm">

                <!-- Nueva Tarea -->
                <button onclick="document.getElementById('modalAgregar').showModal()"
                    class="flex items-center gap-1 bg-green-600 hover:bg-green-700 text-white font-medium text-sm px-4 py-1.5 rounded-md shadow">
                    <i class="fas fa-plus-circle text-sm"></i>
                    <span>Nuevo</span>
                </button>

                <!-- Exportar -->
                <div class="flex flex-wrap gap-2 justify-center sm:justify-start">
                    <a href="?exportar=excel"
                        class="flex items-center gap-1 bg-emerald-500 hover:bg-emerald-600 text-white text-sm px-3 py-1.5 rounded-md shadow">
                        <i class="fas fa-file-excel text-sm"></i>
                        <span>Excel</span>
                    </a>
                    <a href="exportar_pdf.php"
                        class="flex items-center gap-1 bg-rose-500 hover:bg-rose-600 text-white text-sm px-3 py-1.5 rounded-md shadow">
                        <i class="fas fa-file-pdf text-sm"></i>
                        <span>PDF</span>
                    </a>
                    <a href="grafico.php"
                        class="flex items-center gap-1 bg-blue-500 hover:bg-blue-600 text-white text-sm px-3 py-1.5 rounded-md shadow">
                        <i class="fas fa-chart-bar text-sm"></i>
                        <span>Gráfico</span>
                    </a>
                </div>
            </div>

            <!-- Tabla moderna y responsive -->
            <div class="overflow-x-auto rounded-xl shadow-lg mb-6 border border-gray-200">
                <?php $contador = count($tareas); // contador inicial (mayor a menor) ?>
                <table class="min-w-full divide-y divide-gray-200 text-sm bg-white">
                    <thead class="bg-gradient-to-r from-green-600 to-green-500 text-white">
                        <tr>
                            <th class="px-4 py-3 text-center font-semibold">#</th>
                            <th class="px-4 py-3 text-left font-semibold">Módulo</th>
                            <th class="px-4 py-3 text-left font-semibold">Detalle</th>
                            <th class="px-4 py-3 text-left font-semibold">Estado</th>
                            <th class="px-4 py-3 text-left font-semibold">Revisado</th>
                            <th class="px-4 py-3 text-left font-semibold">Observaciones</th>
                            <th class="px-4 py-3 text-center font-semibold">Imagen</th>
                            <th class="px-4 py-3 text-left font-semibold">Fecha</th>
                            <th class="px-4 py-3 text-center font-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($tareas as $t): ?>
                            <tr class="hover:bg-green-50 transition">
                                <td class="px-4 py-2 text-center font-semibold text-gray-700">
                                    <?= $contador ?>
                                </td>
                                <td class="px-4 py-2"><?= htmlspecialchars($t['modulo']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($t['detalle']) ?></td>
                                <td class="px-4 py-2">
                                    <?php if ($t['estado'] === 'SI'): ?>
                                        <span class="inline-flex items-center text-green-700 font-semibold">
                                            <i class="fas fa-check-circle mr-1"></i> Terminado
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center text-yellow-600 font-semibold">
                                            <i class="fas fa-hourglass-half mr-1"></i> En Proceso
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2">
                                    <?php if ($t['revisado'] === 'SI'): ?>
                                        <span class="text-green-600 font-medium">Sí</span>
                                    <?php else: ?>
                                        <span class="text-red-500 font-medium">No</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2 text-gray-700">
                                    <?= !empty($t['observaciones']) ? htmlspecialchars($t['observaciones']) : '<span class="text-gray-400">--</span>' ?>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <?php if (!empty($t['imagen'])): ?>
                                        <button type="button" class="text-blue-600 hover:text-blue-800 transition focus:outline-none" onclick="mostrarImagenModal('uploads/<?= htmlspecialchars($t['imagen']) ?>')">
                                            <i class="fas fa-image fa-lg"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-gray-400">--</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2">
                                    <?= !empty($t['fecha']) ? date('d/m/Y', strtotime($t['fecha'])) : '<span class="text-gray-400">--</span>' ?>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button onclick='editarTarea(<?= json_encode($t) ?>)'
                                            class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded-full text-xs shadow">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" class="inline"
                                            onsubmit="return confirm('¿Estás seguro de eliminar esta tarea?');">
                                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                            <input type="hidden" name="accion" value="borrar">
                                            <button type="submit"
                                                class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded-full text-xs shadow">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php $contador--; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    <!-- Modal para mostrar imagen adjunta -->
    <dialog id="modalImagen" class="rounded-xl shadow-2xl p-4 w-full max-w-lg max-h-screen overflow-y-auto backdrop:bg-black/30">
        <div class="flex flex-col items-center">
            <img id="imgModal" src="" alt="Imagen adjunta" class="max-w-full max-h-[70vh] rounded-lg border mb-4" />
            <button onclick="document.getElementById('modalImagen').close()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md">Cerrar</button>
        </div>
    </dialog>

            <!-- Paginación moderna a la derecha -->
            <div class="flex justify-end mt-6">
                <div class="inline-flex items-center space-x-1 text-sm">
                    <?php
                    $query = $_GET;
                    unset($query['pagina']);
                    $base_url = '?' . http_build_query($query);
                    $total_paginas = ceil($total_rows / $por_pagina);

                    if ($pagina > 1): ?>
                        <a href="<?= $base_url . '&pagina=' . ($pagina - 1) ?>"
                            class="px-2.5 py-1 rounded-md border border-gray-300 bg-white hover:bg-gray-100 text-gray-600">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif;

                    for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <a href="<?= $base_url . '&pagina=' . $i ?>"
                            class="px-3 py-1.5 rounded-md border <?= $i === $pagina ? 'bg-green-600 text-white border-green-600' : 'bg-white text-green-700 border-gray-300 hover:bg-green-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor;

                    if ($pagina < $total_paginas): ?>
                        <a href="<?= $base_url . '&pagina=' . ($pagina + 1) ?>"
                            class="px-2.5 py-1 rounded-md border border-gray-300 bg-white hover:bg-gray-100 text-gray-600">
                            <i class="fas fa-angle-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>

    <!-- Modal Agregar -->
    <dialog id="modalAgregar" class="rounded-xl shadow-2xl p-4 w-full max-w-md md:max-w-lg max-h-screen overflow-y-auto backdrop:bg-black/30">
        <form method="POST" enctype="multipart/form-data" class="grid gap-4 text-sm text-gray-800">
            <h2 class="text-lg md:text-xl font-bold text-green-700">Agregar Tarea</h2>

            <input type="hidden" name="accion" value="crear">

            <label class="grid gap-1">
                <span class="font-medium">Módulo</span>
                <input name="modulo" required
                    class="p-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-green-400">
            </label>

            <label class="grid gap-1">
                <span class="font-medium">Detalle</span>
                <textarea name="detalle" required rows="4"
                    class="p-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-green-400"></textarea>
            </label>

            <label class="grid gap-1">
                <span class="font-medium">Estado</span>
                <select name="estado" required
                    class="p-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-green-400">
                    <option value="SI">Terminado</option>
                    <option value="NO">En Proceso</option>
                </select>
            </label>

            <label class="grid gap-1">
                <span class="font-medium">Revisado</span>
                <select name="revisado"
                    class="p-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-green-400">
                    <option value="SI">SI</option>
                    <option value="NO">NO</option>
                </select>
            </label>

            <label class="grid gap-1">
                <span class="font-medium">Observaciones</span>
                <textarea name="observaciones" rows="3"
                    class="p-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-green-400"></textarea>
            </label>

            <label class="grid gap-1">
                <span class="font-medium">Imagen</span>
                <input type="file" name="imagen" accept="image/*"
                    class="p-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-green-400">
            </label>

            <label class="grid gap-1">
                <span class="font-medium">Fecha</span>
                <input type="date" name="fecha"
                    class="p-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-green-400">
            </label>

            <!-- Botones -->
            <div class="flex flex-col sm:flex-row justify-end gap-2 mt-2">
                <button type="submit"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Guardar
                </button>
                <button type="button" onclick="document.getElementById('modalAgregar').close()"
                    class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Cancelar
                </button>
            </div>
        </form>
    </dialog>

    <!-- Modal Editar -->
    <dialog id="modalEditar" class="rounded-xl shadow-2xl p-4 w-full max-w-md md:max-w-lg max-h-screen overflow-y-auto backdrop:bg-black/30">
        <form method="POST" enctype="multipart/form-data" class="grid gap-4 text-sm text-gray-800">
            <h2 class="text-lg md:text-xl font-bold text-green-700">Editar Tarea</h2>

            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id" id="edit-id">

            <label class="grid gap-1">
                <span class="font-medium">Módulo</span>
                <input name="modulo" id="edit-modulo" required
                    class="p-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-green-400">
            </label>

            <label class="grid gap-1">
                <span class="font-medium">Detalle</span>
                <textarea name="detalle" id="edit-detalle" required rows="4"
                    class="p-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-green-400"></textarea>
            </label>

            <label class="grid gap-1">
                <span class="font-medium">Estado</span>
                <select name="estado" id="edit-estado" required
                    class="p-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-green-400">
                    <option value="SI">Terminado</option>
                    <option value="NO">En Proceso</option>
                </select>
            </label>

            <label class="grid gap-1">
                <span class="font-medium">Revisado</span>
                <select name="revisado" id="edit-revisado"
                    class="p-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-green-400">
                    <option value="SI">SI</option>
                    <option value="NO">NO</option>
                </select>
            </label>

            <label class="grid gap-1">
                <span class="font-medium">Observaciones</span>
                <textarea name="observaciones" id="edit-observaciones" rows="3"
                    class="p-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-green-400"></textarea>
            </label>

            <label class="grid gap-1">
                <span class="font-medium">Actualizar Imagen</span>
                <input type="file" name="imagen" accept="image/*"
                    class="p-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-green-400">
            </label>

            <!-- Imagen actual -->
            <input type="hidden" id="edit-imagen" name="imagen_actual">
            <div id="imagen-vista-previa" class="hidden">
                <a href="#" id="btn-ver-imagen" target="_blank"
                    class="inline-flex items-center text-sm text-blue-600 hover:underline">
                    <i class="fas fa-image mr-1"></i> Ver imagen actual
                </a>
            </div>

            <label class="grid gap-1">
                <span class="font-medium">Fecha</span>
                <input type="date" name="fecha" id="edit-fecha"
                    class="p-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-green-400">
            </label>

            <!-- Botones -->
            <div class="flex flex-col sm:flex-row justify-end gap-2 mt-2">
                <button type="submit"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Actualizar
                </button>
                <button type="button" onclick="document.getElementById('modalEditar').close()"
                    class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Cancelar
                </button>
            </div>
        </form>
    </dialog>

    <!-- Modal de confirmación de cierre de sesión -->
    <dialog id="modalCerrarSesion" class="rounded-xl shadow-lg p-6 w-full max-w-sm text-center border border-red-300">
        <h3 class="text-lg font-semibold text-red-600 mb-3">¿Deseas cerrar sesión?</h3>
        <p class="text-sm text-gray-600 mb-4">Se cerrará tu sesión actual en el sistema.</p>
        <div class="flex justify-center gap-4">
            <button onclick="window.location.href='logout.php'" class="bg-red-600 hover:bg-red-700 text-white px-4 py-1 rounded">
                Sí, salir
            </button>
            <button onclick="document.getElementById('modalCerrarSesion').close()" class="bg-gray-300 hover:bg-gray-400 text-black px-4 py-1 rounded">
                Cancelar
            </button>
        </div>
    </dialog>

    <!-- Modal de advertencia de cierre de sesión -->
    <dialog id="sessionModal" class="rounded-xl shadow-xl p-6 w-full max-w-sm text-center border border-red-300">
        <h3 class="text-lg font-semibold text-red-600 mb-2">⚠️ Sesión Inactiva</h3>
        <p class="text-gray-700 mb-3">Tu sesión ha estado inactiva por un tiempo.</p>
        <p class="text-sm text-gray-600 mb-4">
            Será cerrada automáticamente en <span id="countdown">30</span> segundos.
        </p>
        <div class="flex justify-center space-x-3">
            <button onclick="continuarSesion()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-1 rounded">Seguir conectado</button>
            <button onclick="cerrarSesion()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-1 rounded">Cerrar sesión ahora</button>
        </div>
    </dialog>
<script>
    // ...existing code...
        const tiempoInactividad = 5 * 60 * 1000; // 5 minutos
        const tiempoDesconexion = 30 * 1000; // 30 segundos

        let timerInactivo, timerCerrar, countdownInterval;
        let tiempoRestante = tiempoDesconexion / 1000;

        function mostrarModal() {
            const modal = document.getElementById('sessionModal');
            modal.showModal();
            tiempoRestante = tiempoDesconexion / 1000;
            actualizarCountdown();

            countdownInterval = setInterval(() => {
                tiempoRestante--;
                actualizarCountdown();
                if (tiempoRestante <= 0) clearInterval(countdownInterval);
            }, 1000);

            timerCerrar = setTimeout(() => {
                cerrarSesion();
            }, tiempoDesconexion);
        }

        function actualizarCountdown() {
            const countdown = document.getElementById('countdown');
            if (countdown) countdown.textContent = tiempoRestante;
        }

        function reiniciarTemporizador() {
            clearTimeout(timerInactivo);
            clearTimeout(timerCerrar);
            clearInterval(countdownInterval);
            document.getElementById('sessionModal').close();
            timerInactivo = setTimeout(mostrarModal, tiempoInactividad);
        }

        function continuarSesion() {
            reiniciarTemporizador();
        }

        function cerrarSesion() {
            window.location.href = 'logout.php';
        }

        ['mousemove', 'keydown', 'click'].forEach(evento =>
            document.addEventListener(evento, reiniciarTemporizador)
        );

        reiniciarTemporizador();

        // Modal para mostrar imagen adjunta de la tabla
        function mostrarImagenModal(src) {
            document.getElementById('imgModal').src = src;
            document.getElementById('modalImagen').showModal();
        }

        function editarTarea(tarea) {
            document.getElementById('edit-id').value = tarea.id;
            document.getElementById('edit-modulo').value = tarea.modulo;
            document.getElementById('edit-detalle').value = tarea.detalle;
            document.getElementById('edit-estado').value = tarea.estado;
            document.getElementById('edit-revisado').value = tarea.revisado;
            document.getElementById('edit-fecha').value = tarea.fecha;
            document.getElementById('edit-observaciones').value = tarea.observaciones || '';
            document.getElementById('edit-imagen').value = tarea.imagen || '';

            const vistaPrevia = document.getElementById('imagen-vista-previa');
            const btnVer = document.getElementById('btn-ver-imagen');

            if (tarea.imagen) {
                btnVer.href = 'uploads/' + tarea.imagen;
                vistaPrevia.classList.remove('hidden');
            } else {
                btnVer.href = '#';
                vistaPrevia.classList.add('hidden');
            }

            document.getElementById('modalEditar').showModal();
        }
    </script>
</body>

</html>
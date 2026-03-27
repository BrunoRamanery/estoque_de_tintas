<?php
/**
 * Include para exibir a mensagem flash do sistema.
 * Espera receber a variavel $mensagem.
 */
?>
<?php if (!empty($mensagem)): ?>
    <div class="alerta alerta-<?= e($mensagem['tipo']) ?>">
        <i class="fa-solid <?= $mensagem['tipo'] === 'sucesso' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
        <span><?= e($mensagem['texto']) ?></span>
    </div>
<?php endif; ?>

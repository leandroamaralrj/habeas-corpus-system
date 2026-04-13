<?php

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

$outputDir = __DIR__ . DIRECTORY_SEPARATOR . 'output';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// Modelo fixo na raiz do projeto
$arquivo = 'HABEAS_CORPUS.docx';
$caminhoModelo = __DIR__ . DIRECTORY_SEPARATOR . $arquivo;

if (!is_file($caminhoModelo)) {
    echo 'Arquivo de modelo não encontrado: ' . htmlspecialchars($arquivo, ENT_QUOTES, 'UTF-8');
    exit;
}

// Se o formulário foi enviado para gerar o documento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'gerar') {
    $valores = isset($_POST['campo']) && is_array($_POST['campo']) ? $_POST['campo'] : [];

    $processor = new TemplateProcessor($caminhoModelo);

    foreach ($valores as $nome => $valor) {
        $processor->setValue($nome, $valor);
    }

    $nomeSaidaBase = pathinfo($arquivo, PATHINFO_FILENAME) . '_preenchido_' . date('Ymd_His');
    $nomeSaida = $nomeSaidaBase . '.docx';
    $caminhoSaida = $outputDir . DIRECTORY_SEPARATOR . $nomeSaida;

    $processor->saveAs($caminhoSaida);

    // Força o download do arquivo gerado
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $nomeSaida . '"');
    header('Content-Length: ' . filesize($caminhoSaida));
    readfile($caminhoSaida);
    unlink($caminhoSaida);
    exit;
}

// Carrega variáveis do modelo para exibir na tela
$processor = new TemplateProcessor($caminhoModelo);
$variaveis = $processor->getVariables();

// Gera uma versão HTML do documento para pré-visualização a partir de um template HTML fixo
$templateHtmlPath = __DIR__ . DIRECTORY_SEPARATOR . 'template_habeas.html';
if (is_file($templateHtmlPath)) {
    $htmlPreview = file_get_contents($templateHtmlPath);
} else {
    $htmlPreview = '<p class="text-danger">Arquivo template_habeas.html não encontrado. Crie este arquivo com o HTML do seu modelo para ter uma pré-visualização completa.</p>';
}

// Converte automaticamente sequencias pontilhadas ("....." ou "……")
// em campos editaveis para virar perguntas no fluxo.
$contadorCamposAuto = 1;
$htmlPreview = preg_replace_callback('/(?:\.{3,}|…+)/u', function () use (&$contadorCamposAuto) {
    $nomeCampo = 'CAMPO_AUTO_' . $contadorCamposAuto++;
    return '<span class="campo-editavel" data-campo="' . $nomeCampo . '">${' . $nomeCampo . '}</span>';
}, $htmlPreview);

// Inclui também os campos declarados no HTML (data-campo),
// para que marcadores numericos como "1", "2", "3"... virem perguntas.
$camposDoHtml = [];
if (preg_match_all('/data-campo="([^"]+)"/', $htmlPreview, $matchesHtmlCampos)) {
    $camposDoHtml = $matchesHtmlCampos[1];
}

// Merge preservando ordem: primeiro os campos do HTML, depois os do DOCX
$variaveis = array_values(array_unique(array_merge($camposDoHtml, $variaveis)));

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Preencher modelo - <?php echo htmlspecialchars($arquivo, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require __DIR__ . '/site_header.php'; ?>
<div class="container-fluid py-4">
    <?php if (empty($variaveis)): ?>
        <div class="alert alert-warning">
            Nenhuma variável foi encontrada no modelo.
            Verifique se você usou o formato <code>${NOME_DO_CAMPO}</code> nos locais pontilhados.
        </div>
    <?php else: ?>
        <div class="row">
            <!-- Painel de edição na esquerda -->
            <div class="col-12 col-xl-4 mb-3 painel-perguntas-col">
                <div class="card shadow-sm painel-perguntas-card">
                    <div class="card-body painel-perguntas-body">
                        <form method="post" id="form-preencher">
                            <input type="hidden" name="acao" value="gerar">

                            <div id="bloco-pergunta" class="bloco-pergunta bg-white">
                                <div class="bloco-pergunta-header">
                                    <label for="campo-atual-input" id="label-pergunta" class="form-label fw-semibold mb-0"></label>
                                </div>
                                <div class="bloco-pergunta-body">
                                    <div id="progresso-pergunta" class="small text-muted mb-2 d-none"></div>
                                    <p id="descricao-pergunta" class="mb-3"></p>
                                    <input id="campo-atual-input" class="form-control" type="text" autocomplete="off">
                                    <div class="d-flex gap-2 mt-3">
                                        <button type="button" id="btn-voltar" class="btn btn-secondary flex-fill">
                                            Voltar
                                        </button>
                                        <button type="button" id="btn-pular" class="btn btn-outline-secondary flex-fill">
                                            Pular
                                        </button>
                                        <button type="button" id="btn-proxima" class="btn btn-primary flex-fill">
                                            Próxima Pergunta
                                        </button>
                                    </div>

                                    <div class="separador-progresso-topo"></div>
                                    <div class="painel-rodape-perguntas">
                                        <div class="painel-progresso">
                                            <span class="rotulo-progresso">Progresso:</span>
                                            <div class="trilho-progresso" role="progressbar" aria-label="Progresso do preenchimento">
                                                <div id="barra-progresso" class="preenchimento-progresso" style="width: 0%"></div>
                                            </div>
                                            <span id="texto-progresso" class="valor-progresso">0%</span>
                                        </div>
                                    </div>
                                    <div class="separador-progresso"></div>
                                    <p class="aviso-campos-vazios mb-0">
                                        Campos deixados em branco poderão ser editados posteriormente no contrato.
                                    </p>
                                </div>
                            </div>

                            <div id="acoes-finalizacao" class="d-none mt-3">
                                <div class="d-flex gap-2">
                                    <button type="button" id="btn-voltar-edicao-fora-modal" class="btn btn-secondary flex-fill">
                                        Voltar Edição
                                    </button>
                                    <button type="button" id="btn-gerar" class="btn btn-success flex-fill">
                                        Finalizar preenchimento
                                    </button>
                                </div>
                            </div>
                            <button type="submit" id="btn-submit-final" class="d-none">
                                Enviar
                            </button>

                            <div id="campos-hidden" class="d-none">
                                <?php foreach ($variaveis as $nomeVar): ?>
                                    <input
                                        type="hidden"
                                        class="campo-input"
                                        name="campo[<?php echo htmlspecialchars($nomeVar, ENT_QUOTES, 'UTF-8'); ?>]"
                                        id="input-<?php echo htmlspecialchars($nomeVar, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-campo="<?php echo htmlspecialchars($nomeVar, ENT_QUOTES, 'UTF-8'); ?>"
                                        value="">
                                <?php endforeach; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Pré-visualização do documento -->
            <div class="col-12 col-xl-8 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-header">
                        Pré-visualização do documento
                    </div>
                    <div class="card-body" style="max-height: 80vh; overflow:auto; background:white;">
                        <div id="preview-doc" class="document-preview" onselectstart="return false;">
                            <?php echo $htmlPreview; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<div id="menu-edicao-campo" class="menu-edicao-campo d-none">
    <div class="menu-edicao-titulo" id="menu-edicao-titulo">Editar campo</div>
    <textarea id="menu-edicao-input" class="form-control form-control-sm" rows="3" placeholder="Digite aqui..."></textarea>
    <div class="d-flex gap-2 mt-2">
        <button type="button" id="menu-edicao-salvar" class="btn btn-primary btn-sm flex-fill">Salvar</button>
        <button type="button" id="menu-edicao-limpar" class="btn btn-outline-secondary btn-sm flex-fill">Limpar</button>
        <button type="button" id="menu-edicao-fechar" class="btn btn-light btn-sm flex-fill">Fechar</button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<div class="modal fade" id="modal-finalizacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-4">
                <h2 class="h1 text-center mb-2">Seu documento está pronto!</h2>
                <hr class="my-3">
                <p class="mb-3">
                    O documento personalizado foi criado com base nas suas respostas.
                    Revise, volte para edição se necessário ou continue para gerar o Word.
                </p>
                <div class="mb-3">
                    <label for="email-finalizacao" class="form-label fw-semibold">E-mail para receber o documento (opcional):</label>
                    <input type="email" id="email-finalizacao" class="form-control" placeholder="seu@email.com.br">
                </div>
                <p class="text-muted small mb-3">
                    Ao continuar, o documento Word será gerado para download imediato.
                </p>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-warning flex-fill text-white" id="btn-modal-continuar">
                        Continuar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modal-campos-pendentes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-4 text-center">
                <h5 class="mb-2">Atenção</h5>
                <p class="mb-3">Ainda faltam campos para preencher antes de finalizar.</p>
                <button type="button" class="btn btn-primary px-4" id="btn-ok-campos-pendentes" data-bs-dismiss="modal">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modal-impressao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-4">
                <h3 class="text-center mb-0 fw-semibold">Valor total</h3>
                <div class="text-center valor-total-modal mb-2">R$ 19,90</div>
                <hr class="my-2">
                <h2 class="text-center mb-2">Forma de Pagamento</h2>
                <p class="text-center mb-1">Selecione a forma de pagamento desejada</p>
                <p class="text-center mb-3">Download e envio por e-mail imediatos</p>

                <div class="row g-2 mb-3 opcoes-pagamento">
                    <div class="col-6">
                        <div class="opcao-pagamento ativa">
                            <div class="titulo-opcao">Cartão de Crédito</div>
                            <div class="icone-opcao">💳</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="opcao-pagamento">
                            <div class="titulo-opcao">Pix</div>
                            <div class="icone-opcao">🟩</div>
                        </div>
                    </div>
                </div>

                <hr class="my-3">
                <p class="text-center text-muted mb-2">Você está em um ambiente seguro</p>
                <div class="mb-3"></div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary flex-fill" id="btn-modal-voltar-finalizacao">
                        Voltar
                    </button>
                    <button type="button" class="btn btn-success flex-fill" id="btn-modal-imprimir">
                        Imprimir
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    (function () {
        // Lista de variáveis vinda do PHP para uso no JavaScript
        const CAMPOS = <?php echo json_encode($variaveis ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        let indiceAtual = 0;

        // =================== CONFIGURACAO FACIL DAS PERGUNTAS ===================
        // Edite cada campo separadamente aqui.
        // Chave = nome do campo (ex.: NOME_CLIENTE, CPF_CLIENTE, NUMERO_PROCESSO, 1, 2, 3...)
        // Se um campo nao estiver neste objeto, o sistema usa texto automatico.
        const CONFIG_PERGUNTAS = {
            NOME_CLIENTE: {
                titulo: 'Qual e o nome completo do cliente?',
                descricao: 'Preencha este campo com as informacoes correspondentes.',
                placeholder: 'Digite aqui...'
            },
            CPF_CLIENTE: {
                titulo: 'Qual e o CPF do cliente?',
                descricao: 'Preencha este campo com as informacoes correspondentes.',
                placeholder: 'Digite aqui...'
            }
        };
        // =======================================================================

        const form = document.getElementById('form-preencher');
        const inputPerguntaAtual = document.getElementById('campo-atual-input');
        const labelPergunta = document.getElementById('label-pergunta');
        const descricaoPergunta = document.getElementById('descricao-pergunta');
        const progressoPergunta = document.getElementById('progresso-pergunta');
        const blocoPergunta = document.getElementById('bloco-pergunta');
        const btnPular = document.getElementById('btn-pular');
        const btnProxima = document.getElementById('btn-proxima');
        const btnVoltar = document.getElementById('btn-voltar');
        const btnGerar = document.getElementById('btn-gerar');
        const acoesFinalizacao = document.getElementById('acoes-finalizacao');
        const btnVoltarEdicaoForaModal = document.getElementById('btn-voltar-edicao-fora-modal');
        const btnSubmitFinal = document.getElementById('btn-submit-final');
        const barraProgresso = document.getElementById('barra-progresso');
        const textoProgresso = document.getElementById('texto-progresso');
        const elModalFinalizacao = document.getElementById('modal-finalizacao');
        const elModalCamposPendentes = document.getElementById('modal-campos-pendentes');
        const elModalImpressao = document.getElementById('modal-impressao');
        const btnModalContinuar = document.getElementById('btn-modal-continuar');
        const btnModalVoltarFinalizacao = document.getElementById('btn-modal-voltar-finalizacao');
        const btnModalImprimir = document.getElementById('btn-modal-imprimir');
        const opcoesPagamento = document.querySelectorAll('.opcao-pagamento');
        const menuEdicaoCampo = document.getElementById('menu-edicao-campo');
        const menuEdicaoTitulo = document.getElementById('menu-edicao-titulo');
        const menuEdicaoInput = document.getElementById('menu-edicao-input');
        const menuEdicaoSalvar = document.getElementById('menu-edicao-salvar');
        const menuEdicaoLimpar = document.getElementById('menu-edicao-limpar');
        const menuEdicaoFechar = document.getElementById('menu-edicao-fechar');
        const modalFinalizacao = elModalFinalizacao ? new bootstrap.Modal(elModalFinalizacao) : null;
        const modalCamposPendentes = elModalCamposPendentes ? new bootstrap.Modal(elModalCamposPendentes) : null;
        const modalImpressao = elModalImpressao ? new bootstrap.Modal(elModalImpressao) : null;
        let campoMenuAtual = null;

        function obterResumoProgresso() {
            let preenchidos = 0;
            let primeiroPendente = -1;

            CAMPOS.forEach(function (campo, idx) {
                const inputOculto = obterInputOculto(campo);
                const temValor = !!(inputOculto && inputOculto.value && inputOculto.value.trim() !== '');
                const confirmado = !!(inputOculto && inputOculto.dataset && inputOculto.dataset.confirmado === '1');
                const contaNoProgresso = temValor && confirmado;
                if (contaNoProgresso) {
                    preenchidos++;
                } else if (primeiroPendente === -1) {
                    primeiroPendente = idx;
                }
            });

            const percentual = CAMPOS.length ? Math.round((preenchidos / CAMPOS.length) * 100) : 0;
            return {
                preenchidos: preenchidos,
                total: CAMPOS.length,
                percentual: percentual,
                completo: CAMPOS.length > 0 && preenchidos === CAMPOS.length,
                primeiroPendente: primeiroPendente
            };
        }

        function irParaPrimeiroPendente() {
            const resumo = obterResumoProgresso();
            if (resumo.primeiroPendente === -1) return;

            indiceAtual = resumo.primeiroPendente;
            if (blocoPergunta) blocoPergunta.classList.remove('d-none');
            if (btnPular) btnPular.classList.remove('d-none');
            if (btnProxima) btnProxima.classList.remove('d-none');
            if (btnGerar) btnGerar.classList.add('d-none');
            carregarPergunta();
            if (inputPerguntaAtual) inputPerguntaAtual.focus();
        }

        function atualizarPreviewCampo(campo, valor) {
            document.querySelectorAll('.campo-editavel[data-campo="' + campo + '"]').forEach(function (span) {
                const valorNormalizado = valor ? valor.trim() : '';
                span.textContent = valorNormalizado;
                if (valor && valor.trim() !== '') {
                    span.classList.add('campo-preenchido');
                } else {
                    span.classList.remove('campo-preenchido');
                }
            });
        }

        function destacarCampoNoPreview(campo) {
            document.querySelectorAll('.campo-editavel').forEach(function (span) {
                span.classList.remove('campo-selecionado');
            });
            document.querySelectorAll('.campo-editavel[data-campo="' + campo + '"]').forEach(function (span) {
                span.classList.add('campo-selecionado');
                span.scrollIntoView({behavior: 'smooth', block: 'center'});
            });
        }

        function obterInputOculto(campo) {
            return document.getElementById('input-' + campo);
        }

        function marcarCampoConfirmado(campo, confirmado) {
            const inputOculto = obterInputOculto(campo);
            if (!inputOculto) return;
            inputOculto.dataset.confirmado = confirmado ? '1' : '0';
        }

        function ehCampoNumerico(campo) {
            return /^\d+$/.test(String(campo || '').trim());
        }

        function obterTextoPergunta(campo) {
            if (CONFIG_PERGUNTAS[campo]) {
                return {
                    titulo: CONFIG_PERGUNTAS[campo].titulo || String(campo || '').replaceAll('_', ' '),
                    descricao: CONFIG_PERGUNTAS[campo].descricao || 'Preencha este campo com as informacoes correspondentes.',
                    placeholder: CONFIG_PERGUNTAS[campo].placeholder || 'Digite aqui...'
                };
            }

            if (ehCampoNumerico(campo)) {
                return {
                    titulo: 'Pergunta ' + campo,
                    descricao: 'Informe os dados desta etapa. Texto livre (exemplo: Lorem ipsum dolor sit amet).',
                    placeholder: 'Digite a resposta da pergunta ' + campo + '...'
                };
            }

            const tituloLegivel = String(campo || '').replaceAll('_', ' ');
            return {
                titulo: tituloLegivel,
                descricao: 'Preencha este campo com as informacoes correspondentes.',
                placeholder: 'Digite aqui...'
            };
        }

        function estaPreenchido(campo) {
            const inputOculto = obterInputOculto(campo);
            return !!(inputOculto && inputOculto.value && inputOculto.value.trim() !== '');
        }

        function acharProximoPendente(startIndex) {
            for (let i = startIndex; i < CAMPOS.length; i++) {
                const campo = CAMPOS[i];
                if (!estaPreenchido(campo)) return i;
            }
            return -1;
        }

        function atualizarBarraProgresso() {
            if (!CAMPOS.length) return;
            const resumo = obterResumoProgresso();
            if (barraProgresso) {
                barraProgresso.style.width = resumo.percentual + '%';
                barraProgresso.setAttribute('aria-valuenow', String(resumo.percentual));
            }
            if (textoProgresso) {
                textoProgresso.textContent = resumo.percentual + '%';
                textoProgresso.setAttribute('title', resumo.preenchidos + ' de ' + resumo.total + ' campos preenchidos');
            }
        }

        function fecharMenuEdicao() {
            campoMenuAtual = null;
            if (menuEdicaoCampo) menuEdicaoCampo.classList.add('d-none');
        }

        function abrirMenuEdicao(campo, elementoReferencia) {
            if (!menuEdicaoCampo || !menuEdicaoInput) return;
            campoMenuAtual = campo;
            if (menuEdicaoTitulo) menuEdicaoTitulo.textContent = 'Editar: ' + campo;

            const inputOculto = obterInputOculto(campo);
            menuEdicaoInput.value = inputOculto ? inputOculto.value : '';

            const rect = elementoReferencia.getBoundingClientRect();
            menuEdicaoCampo.style.left = (window.scrollX + rect.left) + 'px';
            menuEdicaoCampo.style.top = (window.scrollY + rect.bottom + 8) + 'px';
            menuEdicaoCampo.classList.remove('d-none');
            menuEdicaoInput.focus();
        }

        function carregarPergunta() {
            if (!CAMPOS.length || indiceAtual >= CAMPOS.length) {
                if (blocoPergunta) blocoPergunta.classList.add('d-none');
                if (btnPular) btnPular.classList.add('d-none');
                if (btnProxima) btnProxima.classList.add('d-none');
                if (btnVoltar) btnVoltar.disabled = false;
                if (acoesFinalizacao) acoesFinalizacao.classList.remove('d-none');
                atualizarBarraProgresso();
                return;
            }

            if (blocoPergunta) blocoPergunta.classList.remove('d-none');
            if (btnPular) btnPular.classList.remove('d-none');
            if (btnProxima) btnProxima.classList.remove('d-none');
            if (acoesFinalizacao) acoesFinalizacao.classList.add('d-none');

            const campo = CAMPOS[indiceAtual];
            const inputOculto = obterInputOculto(campo);
            const valorAtual = inputOculto ? inputOculto.value : '';

            if (progressoPergunta) {
                progressoPergunta.textContent = 'Pergunta ' + (indiceAtual + 1) + ' de ' + CAMPOS.length;
            }
            const textoPergunta = obterTextoPergunta(campo);
            if (labelPergunta) labelPergunta.textContent = textoPergunta.titulo;
            if (descricaoPergunta) descricaoPergunta.textContent = textoPergunta.descricao;
            if (inputPerguntaAtual) inputPerguntaAtual.value = valorAtual;
            if (inputPerguntaAtual) inputPerguntaAtual.placeholder = textoPergunta.placeholder;
            if (btnVoltar) btnVoltar.disabled = indiceAtual === 0;
            destacarCampoNoPreview(campo);
        }

        function salvarPerguntaAtual() {
            if (!CAMPOS.length || indiceAtual >= CAMPOS.length) return;
            const campo = CAMPOS[indiceAtual];
            const inputOculto = obterInputOculto(campo);
            if (!inputOculto || !inputPerguntaAtual) return;
            inputOculto.value = inputPerguntaAtual.value;
            atualizarPreviewCampo(campo, inputOculto.value);
        }

        function sincronizarPreviewInicial() {
            CAMPOS.forEach(function (campo) {
                const inputOculto = obterInputOculto(campo);
                const valorAtual = inputOculto ? inputOculto.value : '';
                atualizarPreviewCampo(campo, valorAtual);
            });
        }

        // Função global para permitir que clique no preview abra a pergunta correspondente
        window.__focoCampo = function (campo) {
            if (!campo) return;
            const novoIndice = CAMPOS.indexOf(campo);
            if (novoIndice === -1) return;
            salvarPerguntaAtual();
            indiceAtual = novoIndice;
            carregarPergunta();
            if (inputPerguntaAtual) inputPerguntaAtual.focus();
        };

        if (inputPerguntaAtual) {
            inputPerguntaAtual.addEventListener('input', function () {
                salvarPerguntaAtual();
            });
        }

        if (btnProxima) {
            btnProxima.addEventListener('click', function () {
                salvarPerguntaAtual();
                if (indiceAtual < CAMPOS.length) {
                    const campoAtual = CAMPOS[indiceAtual];
                    marcarCampoConfirmado(campoAtual, estaPreenchido(campoAtual));
                    atualizarBarraProgresso();
                }
                const proximoPendente = acharProximoPendente(indiceAtual + 1);
                indiceAtual = proximoPendente === -1 ? CAMPOS.length : proximoPendente;
                carregarPergunta();
                if (inputPerguntaAtual && indiceAtual < CAMPOS.length) inputPerguntaAtual.focus();
            });
        }

        if (btnPular) {
            btnPular.addEventListener('click', function () {
                if (inputPerguntaAtual) inputPerguntaAtual.value = '';
                salvarPerguntaAtual();
                if (indiceAtual < CAMPOS.length) {
                    const campoAtual = CAMPOS[indiceAtual];
                    marcarCampoConfirmado(campoAtual, false);
                    atualizarBarraProgresso();
                }
                const proximoPendente = acharProximoPendente(indiceAtual + 1);
                indiceAtual = proximoPendente === -1 ? CAMPOS.length : proximoPendente;
                carregarPergunta();
                if (inputPerguntaAtual && indiceAtual < CAMPOS.length) inputPerguntaAtual.focus();
            });
        }

        if (btnVoltar) {
            btnVoltar.addEventListener('click', function () {
                if (indiceAtual <= 0) return;
                salvarPerguntaAtual();
                indiceAtual--;
                carregarPergunta();
                if (inputPerguntaAtual) inputPerguntaAtual.focus();
            });
        }

        if (btnGerar) {
            btnGerar.addEventListener('click', function () {
                salvarPerguntaAtual();
                const resumo = obterResumoProgresso();
                if (!resumo.completo) {
                    if (modalCamposPendentes) {
                        modalCamposPendentes.show();
                    }
                    irParaPrimeiroPendente();
                    return;
                }
                if (modalFinalizacao) {
                    modalFinalizacao.show();
                }
            });
        }

        if (btnVoltarEdicaoForaModal) {
            btnVoltarEdicaoForaModal.addEventListener('click', function () {
                if (indiceAtual >= CAMPOS.length) {
                    indiceAtual = CAMPOS.length - 1;
                    carregarPergunta();
                    if (inputPerguntaAtual) inputPerguntaAtual.focus();
                }
            });
        }

        if (btnModalContinuar) {
            btnModalContinuar.addEventListener('click', function () {
                salvarPerguntaAtual();
                const resumo = obterResumoProgresso();
                if (!resumo.completo) {
                    if (modalFinalizacao) {
                        modalFinalizacao.hide();
                    }
                    irParaPrimeiroPendente();
                    return;
                }
                if (modalFinalizacao) {
                    modalFinalizacao.hide();
                }
                if (modalImpressao) {
                    modalImpressao.show();
                }
            });
        }

        if (btnModalVoltarFinalizacao) {
            btnModalVoltarFinalizacao.addEventListener('click', function () {
                if (modalImpressao) {
                    modalImpressao.hide();
                }
                if (modalFinalizacao) {
                    modalFinalizacao.show();
                }
            });
        }

        if (btnModalImprimir) {
            btnModalImprimir.addEventListener('click', function () {
                salvarPerguntaAtual();
                if (modalImpressao) {
                    modalImpressao.hide();
                }
                if (btnSubmitFinal) {
                    btnSubmitFinal.click();
                }
            });
        }

        opcoesPagamento.forEach(function (opcao) {
            opcao.addEventListener('click', function () {
                opcoesPagamento.forEach(function (item) {
                    item.classList.remove('ativa');
                });
                this.classList.add('ativa');
            });
        });

        if (menuEdicaoSalvar) {
            menuEdicaoSalvar.addEventListener('click', function () {
                if (!campoMenuAtual) return;
                const inputOculto = obterInputOculto(campoMenuAtual);
                if (inputOculto && menuEdicaoInput) {
                    inputOculto.value = menuEdicaoInput.value;
                    atualizarPreviewCampo(campoMenuAtual, inputOculto.value);
                    atualizarBarraProgresso();
                }

                const indiceCampo = CAMPOS.indexOf(campoMenuAtual);
                if (indiceCampo !== -1) {
                    indiceAtual = indiceCampo;
                    carregarPergunta();
                }
                fecharMenuEdicao();
            });
        }

        if (menuEdicaoLimpar) {
            menuEdicaoLimpar.addEventListener('click', function () {
                if (!campoMenuAtual || !menuEdicaoInput) return;
                menuEdicaoInput.value = '';
            });
        }

        if (menuEdicaoFechar) {
            menuEdicaoFechar.addEventListener('click', fecharMenuEdicao);
        }

        // Clique no documento: foca a pergunta correspondente
        const preview = document.getElementById('preview-doc');
        if (preview) {
            preview.querySelectorAll('.campo-editavel').forEach(function (span) {
                span.addEventListener('click', function (event) {
                    event.preventDefault();
                    const campo = this.getAttribute('data-campo');
                    if (campo && window.__focoCampo) {
                        window.__focoCampo(campo);
                    }
                });
            });

            // Mantém apenas um controle leve para evitar seleção de texto
            preview.addEventListener('selectstart', function (event) {
                const alvo = event.target && event.target.closest ? event.target.closest('.campo-editavel') : null;
                if (!alvo) return;
                event.preventDefault();
            });
        }

        document.addEventListener('click', function (event) {
            if (!menuEdicaoCampo || menuEdicaoCampo.classList.contains('d-none')) return;
            const clicouNoMenu = menuEdicaoCampo.contains(event.target);
            const clicouNoCampo = event.target && event.target.closest ? event.target.closest('.campo-editavel') : null;
            if (!clicouNoMenu && !clicouNoCampo) {
                fecharMenuEdicao();
            }
        });

        if (form) {
            form.addEventListener('submit', function () {
                salvarPerguntaAtual();
            });
        }

        sincronizarPreviewInicial();
        carregarPergunta();
        atualizarBarraProgresso();
    })();
</script>

<style>
    .document-preview {
        font-family: "Times New Roman", serif;
        font-size: 14px;
        line-height: 1.4;
    }
    .painel-perguntas-col {
        max-width: 760px;
        margin-left: auto;
        margin-right: auto;
    }
    .painel-perguntas-card {
        border: 0;
        box-shadow: none !important;
        background: transparent;
    }
    .painel-perguntas-body {
        padding: 0.25rem 0.75rem;
    }
    .bloco-pergunta {
        border: 1px solid #d7dce2;
        border-radius: 8px;
        overflow: hidden;
        background: #f2f2f2;
        max-width: 760px;
        margin: 0 auto;
    }
    .bloco-pergunta-header {
        background: #3d5a80;
        color: #ffffff;
        padding: 20px 24px;
        min-height: 108px;
        display: flex;
        align-items: center;
    }
    .bloco-pergunta-header #label-pergunta {
        color: #ffffff;
        font-size: 2.2rem;
        line-height: 1.2;
        font-weight: 600 !important;
        font-family: Arial, Helvetica, sans-serif;
    }
    .bloco-pergunta-body {
        padding: 26px 24px 24px;
        background: #f2f2f2;
    }
    .bloco-pergunta-body #descricao-pergunta {
        color: #4b5563;
        font-size: 1.2rem;
        line-height: 1.35;
        margin-bottom: 34px !important;
        font-family: Arial, Helvetica, sans-serif;
    }
    #campo-atual-input {
        max-width: 520px;
        height: 42px;
        margin: 0 auto;
        margin-bottom: 80px;
        text-align: center;
        font-size: 1.1rem;
        color: #6b7280;
        background: #f1f1f1;
        border: 1px solid #a7a7a7;
        border-radius: 2px;
    }
    #campo-atual-input::placeholder {
        color: #7d7d7d;
    }
    .campo-editavel {
        background-color: #fff3cd; /* amarelo mais forte, estilo alerta bootstrap */
        border-radius: 2px;
        border-bottom: 1px solid #444;
        padding: 1px 2px;
        display: inline-block;
        min-width: 120px;
        min-height: 1em;
        cursor: pointer;
        user-select: none;
        -webkit-user-select: none;
        -ms-user-select: none;
    }
    .campo-editavel:empty::before {
        content: "\00a0";
    }
    .campo-editavel.campo-selecionado {
        background-color: rgba(0, 123, 255, 0.2);
        border-bottom: 1px solid #0056b3;
    }
    .campo-editavel.campo-preenchido {
        background-color: #d1e7dd;
        border-bottom: 1px solid #198754;
    }
    .campo-editavel.campo-preenchido.campo-selecionado {
        background-color: rgba(0, 123, 255, 0.2);
        border-bottom: 1px solid #0056b3;
    }
    .campo-bloco {
        border-left: 3px solid transparent;
        padding-left: 6px;
    }
    .campo-bloco:focus-within {
        border-left-color: #0d6efd;
        background-color: #f8f9fa;
    }
    .trecho-embacado {
        filter: blur(2.4px);
        -webkit-filter: blur(2.4px);
        user-select: none;
    }
    .separador-progresso {
        border-bottom: 1px solid #dee2e6;
        margin-top: 16px;
        padding-top: 0;
        padding-bottom: 10px;
    }
    .separador-progresso-topo {
        border-top: 1px solid #dee2e6;
        margin-top: 18px;
        padding-top: 12px;
    }
    .painel-rodape-perguntas {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 12px;
    }
    .painel-progresso {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        color: #5c636a;
        font-size: 0.95rem;
        margin-bottom: 2px;
    }
    .rotulo-progresso,
    .valor-progresso {
        white-space: nowrap;
    }
    .trilho-progresso {
        width: 160px;
        height: 14px;
        border: 1px solid #9aa0a6;
        background-color: #e9ecef;
    }
    .preenchimento-progresso {
        height: 100%;
        background-color: #0d6efd;
        transition: width 0.25s ease-in-out;
    }
    .aviso-campos-vazios {
        margin-top: 10px;
        text-align: center;
        color: #6c757d;
        font-size: 0.95rem;
        padding-bottom: 4px;
    }
    .menu-edicao-campo {
        position: absolute;
        z-index: 2000;
        width: 280px;
        background: #fff;
        border: 1px solid #ced4da;
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        padding: 10px;
    }
    .menu-edicao-titulo {
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 0.95rem;
    }
    #modal-finalizacao .modal-content {
        border-radius: 12px;
    }
    #modal-finalizacao .btn-warning {
        background-color: #f25c05;
        border-color: #f25c05;
    }
    #modal-finalizacao .btn-warning:hover {
        background-color: #d84f04;
        border-color: #d84f04;
    }
    #modal-impressao .modal-content {
        border-radius: 12px;
    }
    .valor-total-modal {
        font-size: 2rem;
        color: #3c4f6b;
        line-height: 1.1;
    }
    .opcao-pagamento {
        width: 100%;
        border: 1px solid #ced4da;
        border-radius: 8px;
        padding: 8px;
        text-align: center;
        background: #f8f9fa;
        cursor: pointer;
        transition: border-color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
    }
    .opcao-pagamento.ativa {
        border-color: #0d6efd;
        background: #eef5ff;
        box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.15) inset;
    }
    .titulo-opcao {
        font-weight: 600;
        margin-bottom: 6px;
        color: #4c4c4c;
    }
    .icone-opcao {
        font-size: 2rem;
        line-height: 1;
    }
    @media (max-width: 1199.98px) {
        .painel-perguntas-col {
            margin-left: auto;
            margin-right: auto;
        }
    }
</style>
</body>
</html>



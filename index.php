<?php
// Página inicial: upload de modelo e escolha de modelo existente
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Preenchimento de Modelo Word</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap via CDN para modal e layout simples -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require __DIR__ . '/site_header.php'; ?>
<div class="container py-4 catalog-container">
    <?php
    $arquivoModelo = 'HABEAS_CORPUS.docx';
    $caminhoModelo = __DIR__ . DIRECTORY_SEPARATOR . $arquivoModelo;
    $modeloExiste  = is_file($caminhoModelo);

    // Catálogo (modelo exemplo). Apenas o Habeas Corpus aponta para a funcionalidade real.
    $secaoCatalogo = [
        [
            'titulo' => 'ALUGUEL | LOCAÇÃO | HOSPEDAGEM',
            'itens' => [
                ['descricao' => 'Contrato de hospedagem | Locação de Flat ou Apart Hotel', 'preco' => '24,90', 'link' => '#'],
                ['descricao' => 'Contrato de locação de bens móveis', 'preco' => '19,90', 'link' => '#'],
                ['descricao' => 'Contrato de locação de equipamentos', 'preco' => '24,90', 'link' => '#'],
                ['descricao' => 'Contrato de locação de espaço para evento', 'preco' => '24,90', 'link' => '#'],
                ['descricao' => 'Contrato de locação de imóvel comercial', 'preco' => '29,90', 'link' => '#'],
                ['descricao' => 'Contrato de locação de imóvel residencial', 'preco' => '29,90', 'link' => '#'],
                ['descricao' => 'Contrato de locação de imóvel residencial para temporada', 'preco' => '19,90', 'link' => '#'],
                ['descricao' => 'Contrato de locação de quarto em imóvel residencial', 'preco' => '24,90', 'link' => '#'],
                ['descricao' => 'Contrato de locação de vaga de garagem', 'preco' => '19,90', 'link' => '#'],
                ['descricao' => 'Contrato de locação de veículo', 'preco' => '24,90', 'link' => '#'],
            ],
        ],
        [
            'titulo' => 'SUBLOCAÇÃO',
            'itens' => [
                ['descricao' => 'Contrato de sublocação de imóvel comercial', 'preco' => '24,90', 'link' => '#'],
                ['descricao' => 'Contrato de sublocação de imóvel residencial', 'preco' => '19,90', 'link' => '#'],
            ],
        ],
        [
            'titulo' => 'COMPRA E VENDA',
            'itens' => [
                ['descricao' => 'Contrato de compra e venda', 'preco' => '24,90', 'link' => '#'],
                ['descricao' => 'Contrato de compra e venda de estabelecimento comercial (trespasse)', 'preco' => '24,90', 'link' => '#'],
                ['descricao' => 'Contrato de compra e venda de imóvel', 'preco' => '29,90', 'link' => '#'],
                ['descricao' => 'Contrato de compra e venda de imóvel de gaveta', 'preco' => '29,90', 'link' => '#'],
            ],
        ],
        [
            'titulo' => 'HABEAS CORPUS',
            'itens' => [
                [
                    'descricao' => 'Habeas Corpus – Direito de Apelar em Liberdade (Crime Hediondo)',
                    'preco' => '19,90',
                    'link' => $modeloExiste ? 'preencher.php' : '#',
                ],
                [
                    'descricao' => 'Habeas Corpus - Apreensão de passaporte',
                    'preco' => '24,90',
                    'link' => '#',
                ],
                [
                    'descricao' => 'Habeas Corpus - Calamidade Pública',
                    'preco' => '29,90',
                    'link' => '#',
                ],
                [
                    'descricao' => 'Habeas Corpus Trancamento de Ação Penal',
                    'preco' => '34,90',
                    'link' => '#',
                ],
            ],
        ],
    ];
    ?>

    <?php foreach ($secaoCatalogo as $secao): ?>
        <section class="catalog-secao mb-4">
            <div class="catalog-titulo"><?php echo htmlspecialchars($secao['titulo'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="catalog-divider"></div>

            <div class="catalog-lista">
                <?php foreach ($secao['itens'] as $item): ?>
                    <?php $precoFormatado = 'R$ ' . htmlspecialchars($item['preco'], ENT_QUOTES, 'UTF-8'); ?>
                    <a class="catalog-item" href="<?php echo htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $item['link'] === '#' ? 'aria-disabled="true"' : ''; ?>>
                        <span class="catalog-descricao"><?php echo htmlspecialchars($item['descricao'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="catalog-preco"><?php echo $precoFormatado; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <?php if (!$modeloExiste): ?>
        <div class="alert alert-danger mt-3 mb-0">
            Modelo Word <code><?php echo htmlspecialchars($arquivoModelo, ENT_QUOTES, 'UTF-8'); ?></code> não encontrado.
        </div>
    <?php endif; ?>
</div>

<style>
    .catalog-container {
        max-width: 980px;
    }

    .catalog-secao {
        background: transparent;
    }

    .catalog-titulo {
        font-weight: 700;
        color: #0f766e;
        letter-spacing: 0.2px;
        font-size: 1rem;
        text-transform: uppercase;
        margin-bottom: 6px;
    }

    .catalog-divider {
        height: 2px;
        background: #0f766e;
        margin-bottom: 4px;
    }

    .catalog-lista {
        border-top: 0;
    }

    .catalog-item {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 7px 0;
        border-bottom: 1px solid #e5e7eb;
        text-decoration: none;
        color: #374151;
        font-size: 0.92rem;
    }

    .catalog-item:hover {
        color: #0f766e;
    }

    .catalog-descricao {
        max-width: 74%;
    }

    .catalog-preco {
        color: #0f766e;
        font-weight: 600;
        white-space: nowrap;
        padding-left: 8px;
    }

    @media (max-width: 576px) {
        .catalog-descricao {
            max-width: 62%;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



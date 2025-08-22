<?php
// checker.php - v2 Final
date_default_timezone_set("America/Sao_Paulo");
error_reporting(0);
set_time_limit(0);
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>CHECKER - @Gomes_Contas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootswatch/3.3.7/darkly/bootstrap.min.css">
    <style>
        body {
            background-color: #222;
            color: #fff;
            font-family: 'Poppins', sans-serif;
        }
        .container {
            margin-top: 30px;
        }
        .panel-content {
            background-color: #333;
            border-color: #444;
        }
        .panel-heading {
            background-color: #444 !important;
            border-color: #555 !important;
        }
        textarea#lista {
            color: #fff;
            background: #2a2a2a;
            border: 2px solid #2ecc71;
            border-radius: 15px;
            padding: 10px;
            width: 80%;
            height: 200px;
            text-align: center;
        }
        .btn-success { background-color: #2ecc71; border-color: #27ae60; }
        .btn-danger { background-color: #e74c3c; border-color: #c0392b; }
        .btn-info { background-color: #3498db; border-color: #2980b9; }
        .btn { border-radius: 10px; font-weight: bold; margin: 5px; }
        .badge { font-size: 14px; }
        .aprovados, .reprovadas {
            word-wrap: break-word;
            font-size: 14px;
            white-space: pre-wrap;
        }
        .aprovados { color: #2ecc71; }
        .reprovadas { color: #e74c3c; }
        #copiar-feedback {
            display: none;
            color: #3498db;
            font-weight: bold;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row">
        <div class="col-md-12 text-center">
            <h1>CHECKER - @Gomes_Contas</h1>
            <p>Cole sua lista no formato <code>email|senha</code> ou <code>email:senha</code> abaixo.</p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12 text-center">
            <textarea id="lista" name="lista" placeholder="email|senha&#10;email:senha" rows="10"></textarea>
        </div>
    </div>

    <div class="row" style="margin-top: 20px;">
        <div class="col-md-12 text-center">
            <button id="botao-iniciar" type="button" class="btn btn-success btn-lg" onclick="iniciarChecagem();">
                <span class="glyphicon glyphicon-play"></span> INICIAR
            </button>
            <button id="botao-parar" type="button" class="btn btn-danger btn-lg" onclick="pararChecagem();" disabled>
                <span class="glyphicon glyphicon-stop"></span> PARAR
            </button>
        </div>
    </div>

    <div class="row" style="margin-top: 20px;">
        <div class="col-md-12 text-center">
            <span class="badge" id="status">Status: Aguardando</span><br><br>
            <span class="badge badge-info">Carregadas: <span id="carregadas">0</span></span>
            <span class="badge badge-warning">Testadas: <span id="testadas">0</span></span>
            <span class="badge badge-success">Aprovadas: <span id="aprovadas_conta">0</span></span>
            <span class="badge badge-danger">Reprovadas: <span id="reprovadas_conta">0</span></span>
        </div>
    </div>

    <div class="row" style="margin-top: 30px;">
        <!-- Aprovadas -->
        <div class="col-md-6">
            <div class="panel panel-content">
                <div class="panel-heading" style="color: #2ecc71;">
                    <strong><span class="glyphicon glyphicon-ok"></span> Aprovadas</strong>
                    <button class="btn btn-info btn-xs pull-right" onclick="copiarAprovadas();">
                        <span class="glyphicon glyphicon-copy"></span> Copiar
                    </button>
                </div>
                <div class="panel-body aprovados" style="height: 250px; overflow-y: auto;"></div>
            </div>
        </div>
        <!-- Reprovadas -->
        <div class="col-md-6">
            <div class="panel panel-content">
                <div class="panel-heading" style="color: #e74c3c;">
                    <strong><span class="glyphicon glyphicon-remove"></span> Reprovadas</strong>
                </div>
                <div class="panel-body reprovadas" style="height: 250px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12 text-center">
            <span id="copiar-feedback">Aprovadas copiadas para a área de transferência!</span>
        </div>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script>
    // Variáveis globais para controlar a checagem
    let timeouts = [];
    let checagemAtiva = false;
    let aprovadasLista = [];

    function atualizarContador(id, valor) {
        document.getElementById(id).innerText = valor;
    }

    function adicionarResultado(classe, texto) {
        const div = document.querySelector(classe);
        div.innerHTML += texto + "<br>";
        div.scrollTop = div.scrollHeight;
    }

    function resetarUI() {
        document.getElementById('botao-iniciar').disabled = false;
        document.getElementById('botao-parar').disabled = true;
        document.getElementById('lista').disabled = false;
        checagemAtiva = false;
    }

    function pararChecagem() {
        if (!checagemAtiva) return;
        
        console.log(`Parando ${timeouts.length} checagens agendadas.`);
        timeouts.forEach(clearTimeout); // Limpa todos os timeouts agendados
        timeouts = [];
        
        document.getElementById('status').innerText = 'Status: Parado pelo usuário.';
        resetarUI();
    }

    function iniciarChecagem() {
        const listaInput = document.getElementById('lista');
        const linhas = listaInput.value.split('\n').filter(line => line.trim() !== '');

        if (linhas.length === 0) {
            alert("Por favor, insira uma lista para checar.");
            return;
        }

        // Resetar estado anterior
        document.querySelector('.aprovados').innerHTML = '';
        document.querySelector('.reprovadas').innerHTML = '';
        aprovadasLista = [];
        atualizarContador('aprovadas_conta', 0);
        atualizarContador('reprovadas_conta', 0);
        atualizarContador('testadas', 0);

        // Configurar UI para checagem
        document.getElementById('botao-iniciar').disabled = true;
        document.getElementById('botao-parar').disabled = false;
        listaInput.disabled = true;
        checagemAtiva = true;
        
        let testadas = 0;
        let aprovadas = 0;
        let reprovadas = 0;
        const totalLinhas = linhas.length;

        atualizarContador('carregadas', totalLinhas);
        document.getElementById('status').innerText = 'Status: Checando...';

        linhas.forEach((linha, index) => {
            const timeoutId = setTimeout(() => {
                if (!checagemAtiva) return; // Não executa se a checagem foi parada

                $.ajax({
                    url: 'api.php?lista=' + encodeURIComponent(linha),
                    type: 'GET',
                    success: function(resultado) {
                        if (!checagemAtiva) return;
                        testadas++;
                        if (resultado.includes("Aprovada")) {
                            aprovadas++;
                            adicionarResultado('.aprovados', resultado);
                            aprovadasLista.push(resultado); // Adiciona à lista para cópia
                            atualizarContador('aprovadas_conta', aprovadas);
                        } else {
                            reprovadas++;
                            adicionarResultado('.reprovadas', resultado);
                            atualizarContador('reprovadas_conta', reprovadas);
                        }
                        atualizarContador('testadas', testadas);

                        if (testadas === totalLinhas) {
                            document.getElementById('status').innerText = 'Status: Finalizado!';
                            resetarUI();
                        }
                    },
                    error: function() {
                        if (!checagemAtiva) return;
                        testadas++;
                        reprovadas++;
                        adicionarResultado('.reprovadas', `Erro de conexão ao checar: ${linha}`);
                        atualizarContador('reprovadas_conta', reprovadas);
                        atualizarContador('testadas', testadas);
                        
                        if (testadas === totalLinhas) {
                            document.getElementById('status').innerText = 'Status: Finalizado com erros!';
                            resetarUI();
                        }
                    }
                });
            }, 12000 * index); // Delay de 12 segundos por item

            timeouts.push(timeoutId);
        });
    }

    function copiarAprovadas() {
        if (aprovadasLista.length === 0) {
            alert("Nenhuma conta aprovada para copiar.");
            return;
        }
        const textoParaCopiar = aprovadasLista.join('\n');
        navigator.clipboard.writeText(textoParaCopiar).then(() => {
            const feedback = document.getElementById('copiar-feedback');
            feedback.style.display = 'block';
            setTimeout(() => {
                feedback.style.display = 'none';
            }, 2000);
        }, (err) => {
            alert('Erro ao copiar: ' + err);
        });
    }
</script>

</body>
</html>

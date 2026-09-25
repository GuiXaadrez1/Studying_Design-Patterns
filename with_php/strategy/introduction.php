<?php

/**
 * ==============================================================================
 * PADRÃO DE PROJETO: STRATEGY (ESTRATÉGIA)
 * ==============================================================================
 * 
 * --- O QUE É? ---
 * Define uma família de algoritmos, encapsula cada um deles e os torna intercambiáveis.
 * O Strategy permite que o algoritmo varie independentemente dos clientes que o utilizam.
 * 
 * --- ABORDAGEM DO PADRÃO ---
 * 1. Identifique o comportamento que varia (ex: cálculo de frete ou pagamento) e extraia-o 
 *    da classe principal.
 * 2. Crie uma interface que represente essa ação/algoritmo (`EstrategiaPagamentoInterface`).
 * 3. Encapsule cada variação do algoritmo em sua própria classe concreta.
 * 4. O Objeto Contexto (`Pedido`) guarda uma referência para a interface da estratégia 
 *    e delega a execução para ela em runtime.
 * 
 * --- PRINCÍPIOS DE DESIGN APLICADOS ---
 * 1. "Separe o que varia daquilo que permanece igual."
 * 2. "Programe para interfaces, não para implementações."
 * 3. "Prefira composição a herança."
 * 4. "Classes devem estar abertas para extensão e fechadas para modificação (OCP)."
 * 5. "Projete sistemas com acoplamento fraco entre objetos que interagem."
 * 
 * --- QUANDO USAR? ---
 * - Quando você tem variantes de um mesmo algoritmo e precisa alternar entre elas em runtime.
 * - Quando uma classe possui muitos condicionais (`if/else` ou `switch`) para selecionar 
 *   diferentes comportamentos ou regras de negócio.
 * - Para isolar a lógica de negócio ou detalhes de implementação dos algoritmos do código principal.
 * - Casos de uso comuns no PHP:
 *   a) Gateways de Pagamento: alternar entre Pix, Cartão de Crédito e Boleto no checkout.
 *   b) Calculadoras de Frete: alternar cálculos entre Correios, Sedex e Transportadoras.
 *   c) Exportadores de Dados: alternar exportação entre formatos PDF, CSV e JSON.
 */

// ==============================================================================
// PASSO 1: Interface da Estratégia
// ==============================================================================
/**
 * Interface comum para todos os algoritmos suportados.
 * Garante o acoplamento fraco: o Contexto só conhece este contrato.
 */
interface EstrategiaPagamentoInterface 
{
    public function pagar(float $valor): bool;
}

// ==============================================================================
// PASSO 2: Estratégias Concretas (Algoritmos Encapsulados)
// ==============================================================================

class PagamentoPix implements EstrategiaPagamentoInterface 
{
    public function pagar(float $valor): bool 
    {
        echo "[PIX] Gerando QR Code para pagamento no valor de R${$valor}...\n";
        return true;
    }
}

class PagamentoCartaoCredito implements EstrategiaPagamentoInterface 
{
    public function __construct(
        private string $numeroCartao
    ) {}

    public function pagar(float $valor): bool 
    {
        echo "[CARTÃO] Processando R${$valor} no cartão terminado em " . substr($this->numeroCartao, -4) . "...\n";
        return true;
    }
}

class PagamentoBoleto implements EstrategiaPagamentoInterface 
{
    public function pagar(float $valor): bool 
    {
        echo "[BOLETO] Gerando linha digitável para R${$valor}...\n";
        return true;
    }
}

// ==============================================================================
// PASSO 3: O Contexto (Objeto que utiliza a Estratégia)
// ==============================================================================

class Pedido 
{
    // A Injeção da Estratégia via Interface garante o acoplamento fraco
    public function __construct(
        private float $valorTotal,
        private ?EstrategiaPagamentoInterface $estrategiaPagamento = null
    ) {}

    /**
     * Permite alterar a estratégia dinamicamente em tempo de execução (Setter Injection)
     */
    public function setEstrategiaPagamento(EstrategiaPagamentoInterface $estrategia): void 
    {
        $this->estrategiaPagamento =$estrategia;
    }

    public function finalizarCompra(): void 
    {
        if (!$this->estrategiaPagamento) {
            throw new Exception("Selecione uma forma de pagamento para finalizar o pedido.");
        }

        // Delegação: o Pedido não calcula ou processa a forma de pagamento diretamente
        $sucesso = $this->estrategiaPagamento->pagar($this->valorTotal);

        if ($sucesso) {
            echo "[PEDIDO] Compra de R${$this->valorTotal} finalizada com sucesso!\n";
        }
    }
}

// ==============================================================================
// PASSO 4: Execução / Demonstração Prática
// ==============================================================================

$pedido = new Pedido(250.00);

echo "--- Cenário 1: Pagando com Pix ---\n";
$pedido->setEstrategiaPagamento(new PagamentoPix());$pedido->finalizarCompra();

echo "\n--- Cenário 2: Alterando a estratégia em tempo de execução para Cartão ---\n";
$pedido->setEstrategiaPagamento(new PagamentoCartaoCredito("4111111111111234"));
$pedido->finalizarCompra();
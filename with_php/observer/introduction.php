<?php

/**
 * ==============================================================================
 * PADRÃO DE PROJETO: OBSERVER (OBSERVADOR / SUJEITO-ASSINANTE)
 * ==============================================================================
 * 
 * --- O QUE É? ---
 * Define uma relação um-para-muitos entre objetos, de forma que quando um objeto 
 * (Sujeito) muda de estado, todos os seus dependentes (Observadores) são 
 * notificados e atualizados automaticamente.
 * 
 * --- ABORDAGEM DO PADRÃO ---
 * 1. O Sujeito (Subject) mantém uma lista de referências a objetos que implementam 
 *    a interface Observer.
 * 2. Quando o estado do Sujeito muda, ele percorre a lista chamando o método `update()`.
 * 3. Abordagem PULL (utilizada abaixo): O Sujeito apenas avisa que mudou passando sua 
 *    própria referência (`$this`), e cada Observador busca (pull) apenas os atributos 
 *    específicos que necessita via métodos públicos (getters).
 * 
 * --- PRINCÍPIOS DE DESIGN APLICADOS ---
 * 1. "Separe o que varia daquilo que permanece igual."
 * 2. "Programe para interfaces, não para implementações."
 * 3. "Prefira composição a herança."
 * 4. "Classes devem estar abertas para extensão e fechadas para modificação (OCP)."
 * 5. "Projete sistemas com acoplamento fraco entre objetos que interagem."
 * 
 * --- QUANDO USAR? ---
 * - Quando a alteração no estado de um objeto exige mudanças em outros objetos sem saber 
 *   quantos objetos precisam mudar ou quais são eles com antecedência.
 * - Quando um objeto deve ser capaz de notificar outros objetos mantendo o acoplamento fraco.
 * - Quando a relação de escuta precisa ser dinâmica (adicionar/remover ouvintes em runtime).
 * - Casos de uso comuns no PHP:
 *   a) Arquitetura Orientada a Eventos / Webhooks: Notificar serviços quando um pedido é pago.
 *   b) Auditoria e Logs: Gravar históricos de alterações em entidades do sistema.
 *   c) Disparo de Notificações: Enviar e-mail, SMS e atualizar estoque ao finalizar uma compra.
 */

// ==============================================================================
// PASSO 1: O Sujeito Concreto (Estação Meteorológica)
// ==============================================================================
/**
 * O Sujeito gerencia o estado (temperatura, umidade) e mantém uma lista de assinantes.
 * Implementamos a interface nativa do PHP: \SplSubject
 */
class EstacaoMeteorologica implements \SplSubject 
{
    // Armazena a lista de observadores cadastrados
    private \SplObjectStorage $observadores;

    // Estado interno do objeto
    private float $temperatura = 0.0;
    private float $umidade = 0.0;

    public function __construct() 
    {
        // SplObjectStorage é uma coleção eficiente do PHP para armazenar objetos únicos
        $this->observadores = new \SplObjectStorage();
    }

    /**
     * Cadastra um novo observador (Inscrever)
     */
    public function attach(\SplObserver $observer): void 
    {
        $this->observadores->attach($observer);
        echo "[ESTAÇÃO] Novo observador registrado.\n";
    }

    /**
     * Remove um observador (Cancelar inscrição)
     */
    public function detach(\SplObserver $observer): void 
    {
        $this->observadores->detach($observer);
        echo "[ESTAÇÃO] Observador removido.\n";
    }

    /**
     * Notifica todos os observadores cadastrados quando o estado muda.
     * Repare: Não há chamadas diretas a classes concretas aqui!
     */
    public function notify(): void 
    {
        echo "\n[ESTAÇÃO] Notificando observadores sobre a mudança de clima...\n";
        foreach ($this->observadores as$observer) {
            // Passamos a nós mesmos ($this) para que o observador possa buscar os dados (Model Pull)
            $observer->update($this);
        }
    }

    /**
     * Método de negócio: Altera o estado das medições e dispara a notificação
     */
    public function setMedicoes(float $temperatura, float$umidade): void 
    {
        $this->temperatura =$temperatura;
        $this->umidade =$umidade;

        // Dispara o evento automaticamente sempre que as medições mudam
        $this->notify();
    }

    // Getters para que os Observadores possam buscar (Pull) as informações que precisam
    public function getTemperatura(): float 
    {
        return $this->temperatura;
    }

    public function getUmidade(): float 
    {
        return $this->umidade;
    }
}

// ==============================================================================
// PASSO 2: Observadores Concretos (Telas e Alertas)
// ==============================================================================

/**
 * Observador 1: Painel de Condições Atuais
 */
class PainelCondicoesAtuais implements \SplObserver 
{
    public function update(\SplSubject $subject): void 
    {
        // Garantimos que o Sujeito que nos notificou é do tipo que esperamos
        if ($subject instanceof EstacaoMeteorologica) {
            $temp = $subject->getTemperatura();
            $umidade = $subject->getUmidade();             echo "  -> [PAINEL ATUAL] Clima agora: {$temp}°C e {$umidade}% de umidade.\n";
        }
    }
}

/**
 * Observador 2: Sistema de Alerta de Temperatura Extrema
 */
class AlertaClimaticoSystem implements \SplObserver 
{
    public function update(\SplSubject $subject): void 
    {
        if ($subject instanceof EstacaoMeteorologica) {
            $temp = $subject->getTemperatura();
            
            // Este observador tem uma regra de negócio própria baseada no mesmo evento
            if ($temp > 35.0) {
                echo "  -> [ALERTA] CRÍTICO! Temperatura muito alta: {$temp}°C!\n";
            }
        }
    }
}

/**
 * Observador 3: Módulo de Log/Métricas
 */
class LoggerClimatico implements \SplObserver 
{
    public function update(\SplSubject $subject): void 
    {
        if ($subject instanceof EstacaoMeteorologica) {
            echo "  -> [LOG] Registro gravado: Temp={$subject->getTemperatura()}C \vert{} Umidade={$subject->getUmidade()}%\n";
        }
    }
}

// ==============================================================================
// PASSO 3: Execução / Demonstração Prática
// ==============================================================================

// 1. Instanciamos o Sujeito
$estacao = new EstacaoMeteorologica();

// 2. Instanciamos os Observadores
$painel = new PainelCondicoesAtuais();
$alerta = new AlertaClimaticoSystem();$logger = new LoggerClimatico();

// 3. Cadastramos os observadores no sujeito
$estacao->attach($painel);
$estacao->attach($alerta);
$estacao->attach($logger);

// 4. Primeira alteração de clima (Temperatura normal)
$estacao->setMedicoes(25.5, 60.0);

// 5. Segunda alteração de clima (Dispara o alerta no segundo observador)
$estacao->setMedicoes(38.0, 45.0);

// 6. Removendo um observador dinamicamente
echo "\n--- Cancelando assinatura do Logger ---\n";
$estacao->detach($logger);

// 7. Terceira alteração (O Logger não será mais chamado)
$estacao->setMedicoes(22.0, 80.0);
<?php

    /*
        RESUMO:

            - Os Decoradores tem o mesmo supertipo que os objetos que eles decoram

            - Você pode usar um ou mais decoradores para englobar um objeto

            - Uma vez que o decorador tem o mesmo supertipo que o objeto decorado,
                podemos passar um objeto decorado no lugar do objeto original (englobado)

            - O decorador adiciona seu próprio comportamento antes e/ou depois de delegar
                para o objeto que ele decora o resto do trabalho
                
            - Os objetos podem ser decorados a qualquer momento, então podemos decorar 
                os OBJETOS DE MANEIRA DINÂMICA NO TEMPO DE EXECUÇÃO com quantos decoradores
                deserjarmos.

            - DEFINIÇÃO DO PADRÃO DECORATOR:

                -> Anexa responsabilidades adicionais a um objeto dinamicamente. 
                -> Os decoradores fornecem um alternativa flexível de subclasses 
                -> para estender funcionalidades
    */

    /**
     * ==============================================================================
     * PADRÃO DE PROJETO: DECORATOR (DECORADOR / ENVOLTÓRIO / WRAPPER)
     * ==============================================================================
     * 
     * --- O QUE É? ---
     * O Decorator é um padrão estrutural que permite adicionar novos comportamentos 
     * a objetos de forma DINÂMICA, envelopando-os (wrapping) dentro de objetos decoradores.
     * 
     * --- POR QUE USAR? (E NÃO HERANÇA TRADICIONAL?) ---
     * 1. Evita a "explosão de subclasses": Se usássemos herança pura para combinar 
     *    notificações (Email, SMS, WhatsApp, Slack), precisaríamos de classes como:
     *    - NotificadorEmailSMS
     *    - NotificadorEmailWhatsApp
     *    - NotificadorEmailSMSWhatsAppSlack... (Inviável!)
     * 
     * 2. Princípio Aberto/Fechado (OCP): Podemos criar novos decoradores (ex: NotificadorTelegram)
     *    sem alterar NENHUMA linha das classes de notificação existentes.
     * 
     * 3. Composição sobre Herança: A herança é estática (definida em tempo de compilação). 
     *    A composição via Decorator permite montar os objetos em tempo de execução (runtime).
     * 
     * --- QUANDO USAR? ---
     * - Quando você precisa adicionar responsabilidades a objetos individuais de forma dinâmica 
     *   e transparente, sem afetar outros objetos.
     * - Quando a extensão por meio de herança é impraticável ou geraria muitas subclasses.
     * - Casos de uso comuns:
     *   a) Middleware em frameworks HTTP (adicionar log, autenticação, cache na requisição).
     *   b) Formatadores    de texto/Mídia (adicionar HTML, Criptografia, Compressão a um stream).
     *   c) Calculadoras de preços/impostos (adicionar taxas extras ou descontos a um produto).
     */

    // ==============================================================================
    // PASSO 1: A Interface / Componente Base
    // ==============================================================================
    /**
     * Define o contrato comum tanto para o objeto principal quanto para os decoradores.
     * É ISSO que garante o Princípio 2 ("Dê prioridade para Interface, não para implementação").
     */
    interface NotificadorInterface 
    {
        public function enviar(string $mensagem): void;
    }

    // ==============================================================================
    // PASSO 2: O Componente Concreto (Objeto Base)
    // ==============================================================================
    /**
     * Esta é a classe básica que realiza a operação padrão (o núcleo fixo).
     * Ela não sabe que pode ser decorada no futuro.
     */
    class NotificadorEmail implements NotificadorInterface 
    {
        public function enviar(string $mensagem): void 
        {
            // Comportamento base: Envio de e-mail básico
            echo "[E-MAIL] Enviando mensagem: {$mensagem}\n";
        }
    }

    // ==============================================================================
    // PASSO 3: O Decorator Abstrato (A "Ponte")
    // ==============================================================================
    /**
     * A classe abstrata Decorator implementa a mesma interface do objeto base.
     * AQUI ESTÁ O SEGREDO DO PADRÃO:
     * 1. Herda a Interface para MANTER O MESMO TIPO (compatibilidade).
     * 2. Usa COMPOSIÇÃO para guardar uma referência do objeto decorado ($notificador).
     */
    abstract class NotificadorDecorator implements NotificadorInterface 
    {
        // Injeção de Dependência via Construtor: Guarda a instância que está sendo "envelopada"
        public function __construct(
            protected NotificadorInterface $notificador
        ) {}

        /**
         * Delegamos a chamada padrão para o objeto interno.
         * Os decoradores concretos irão sobrescrever esse método para adicionar seu próprio comportamento.
         */
        public function enviar(string $mensagem): void 
        {
            $this->notificador->enviar($mensagem);
        }
    }

    // ==============================================================================
    // PASSO 4: Decoradores Concretos (Adicionam as funcionalidades extras)
    // ==============================================================================

    /**
     * Decorador 1: Adiciona o envio por SMS
     */
    class SMSDecorator extends NotificadorDecorator 
    {
        public function enviar(string $mensagem): void 
        {
            // 1. Executa a notificação do objeto envolvido (pode ser o Email ou outro Decorator)
            parent::enviar($mensagem);
            
            // 2. Adiciona o novo comportamento específico deste decorador
            $this->enviarSMS($mensagem);
        }

        private function enviarSMS(string $mensagem): void 
        {
            echo "[SMS] Enviando SMS com o texto: {$mensagem}\n";
        }
    }

    /**
     * Decorador 2: Adiciona o envio por WhatsApp
     */
    class WhatsAppDecorator extends NotificadorDecorator 
    {
        public function enviar(string $mensagem): void 
        {
            parent::enviar($mensagem);
            $this->enviarWhatsApp($mensagem);
        }

        private function enviarWhatsApp(string $mensagem): void 
        {
            echo "[WHATSAPP] Enviando mensagem no Zap: {$mensagem}\n";
        }
    }

    /**
     * Decorador 3: Adiciona funcionalidade de Log (Exemplo de comportamento interceptador)
     */
    class LogDecorator extends NotificadorDecorator 
    {
        public function enviar(string $mensagem): void 
        {
            // Pode executar algo ANTES do envio
            $this->registrarLog("Iniciando envio do alerta.");
            
            parent::enviar($mensagem);
            
            // Ou DEPOIS do envio
            $this->registrarLog("Alerta enviado com sucesso.");
        }

        private function registrarLog(string $acao): void 
        {
            echo "[LOG] " . date('Y-m-d H:i:s') . " - {$acao}\n";
        }
    }

    // ==============================================================================
    // PASSO 5: Demonstração de Uso Prático (Injeção de Dependência / IoC em Ação)
    // ==============================================================================

    echo "--- CENÁRIO 1: Usuário quer apenas E-mail ---\n";
    $notificadorSimples = new NotificadorEmail();
    $notificadorSimples->enviar("Seu pedido foi faturado!");

    echo "\n--- CENÁRIO 2: Usuário quer E-mail + SMS ---\n";
    // Envelopamos o NotificadorEmail com o SMSDecorator
    $notificadorComSms = new SMSDecorator(new NotificadorEmail());
    $notificadorComSms->enviar("Seu pagamento foi aprovado!");

    echo "\n--- CENÁRIO 3: Usuário VIP (E-mail + SMS + WhatsApp + Logs) ---\n";
    /**
     * Empilhamos os decoradores dinamicamente!
     * A ordem de execução será de DENTRO para FORA na montagem, 
     * ou seja: Log -> WhatsApp -> SMS -> Email
     */
    $notificadorCompleto = new LogDecorator(
        new WhatsAppDecorator(
            new SMSDecorator(
                new NotificadorEmail()
            )
        )
    );

    // O cliente chama apenas 'enviar()', totalmente alheio à complexidade das camadas internas!
    $notificadorCompleto->enviar("Alerta de segurança: Novo login detectado!");

?>
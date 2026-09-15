# Introdução

Esse padrão mantém os objetos atualizados quando algo importante ocorre.
O objeto pode decidir em tempo de execução se deseja ser mantido informado.

## Definição:

Observer: é um padrão de projeto comportamental usado quando um objeto precisa avisar automaticamente vários outros objetos sobre mudanças no seu estado.

Define a dependência (dependency) um para muitos (1:N) entre os objetos, para que quando um objeto mude de estado TODOS OS SEUS DEPENDENTES SEJAM AVISADOS E ATUALIZADOS AUTOMATICAMENTE!

## Exemplo:

### Objeto Subject -> Gerencia alguns bits de dados: (2 int)

              +-------------------+
              |      Subject      |
              |-------------------|
              |    2 int (dados)  |
              +---------+---------+
                        |
                        | dados mudaram
                        |
                        v
              +-------------------+
              |    Notificação    |
              +---------+---------+
                        |
              +---------+---------+
              |         |         |
              v         v         v
        +---------+ +---------+ +---------+
        |Observer | |Observer | |Observer |
        |    1    | |    2    | |    3    |
        +---------+ +---------+ +---------+


- Quando os dados no Objeto Subject mudam, os Objetos Observadores são avisados/notificados.

- Novos valores de dados são comunicados para os observadores de alguma forma quando são alterados.

### Objeto Observer -> Os objetos observadores registraram-se ao Objeto Subject... Isto é, se relacionam com o Objeto Subject por dependência para receber atualizações quando os dados do Subject são alterados.

Subject
   |
   |  dados alterados
   |
   +--------------------+
   |                    |
   v                    v
Observer 1           Observer 2
   |                    |
   +---------+----------+
             |
             v
        Observer 3

**Tudo isso pode ocorrer em tempo de execução!**
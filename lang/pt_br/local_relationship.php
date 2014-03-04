<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'relationship'
 *
 * @package    local_relationship
 * @subpackage relationship
 * @copyright  2010 Petr Skoda (info@skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Relacionamentos';
$string['role1'] = 'Papel 1';
$string['role2'] = 'Papel 2';
$string['cohort1'] = 'Coorte 1';
$string['cohort2'] = 'Coorte 2';
$string['allocated'] = ' (já utilizados em outro grupo)';
$string['notallocated'] = ' (ainda não utilizados)';
$string['viewreport'] = 'Vizualizar';
$string['uniformdistribute'] = 'Distribuição uniforme';
$string['uniformdistribution'] = 'Distribuição uniforme para o coorte 2';
$string['enable_uniformdistribution'] = 'Abilitar distribuição uniforme';
$string['disable_uniformdistribution'] = 'Desabilitar destribuição uniforme';
$string['enabled'] = 'Ativado';
$string['disabled'] = 'Desativado';
$string['cohortid1'] = 'Coorte 1';
$string['cohortid1_help'] = 'Apenas membros deste coorte estão associados ao papel 1.
   Estes membros são pessoas aptas a ensinar e dar tutoria para os estudantes.
   Geralmente há apenas uma pessoa com esse papel em cada grupo.';
$string['cohortid2'] = 'Coorte 2';
$string['cohortid2_help'] = 'Apenas membros desde coorte estão associados ao papel 2.
   Estes membros (estudantes por instancia) são pessoas que recebem atenção dos membros do coorte 1. 
   Cada membro deste coorte está associado a apenas um grupo.';
$string['tochangegroups'] = 'Para mudar grupos de relacionamentos \'{$a}\' é necessário, primeiro, desabilitar a destribuição uniforme dos membros.
   Após você terá que reabilitar manualmente.<BR><BR>Você gostaria de desabilitar a destribuição uniforme para os relacionamento \'{$a}\'?';
$string['groups_unchangeable'] = 'Os grupos não podem ser alterados porque a distribuição uniforme está ativa para este relacionamento';
$string['addgroup'] = 'Adicionar novo grupo';
$string['addgroups'] = 'Adicionar groupo';
$string['addgroupstitle'] = 'Adicionar grupo no relacionamento \'{$a}\'';
$string['addrelationship'] = 'Adicionanar novo relacionamento';
$string['anyrelationship'] = 'Qualquer';
$string['assign'] = 'Atribuir';
$string['courses'] = 'Cursos';
$string['assignto'] = 'Membros do relacionamento \'{$a}\'';
$string['backtorelationship'] = 'Voltar para o relacionamento';
$string['backtorelationships'] = 'Voltar para relacionamentos';
$string['bulkadd'] = 'Adicionar relacionamento';
$string['bulknorelationship'] = 'Nenhum relacionamento disponível encontrado';
$string['relationshipname'] = 'Relacionamento \'{$a}\'';
$string['relationship'] = 'Relacionamento';
$string['relationships'] = 'Relacionamentos';
$string['relationshipgroups'] = 'Lista de grupos do relacionamento \'{$a}\'';
$string['relationshipcourses'] = 'Lista de cursos para este relacionamento';
$string['relationshipsin'] = '{$a}: Relacionamentos disponíveis';
$string['relationship:assign'] = 'Designar membros do relacionamento';
$string['relationship:manage'] = 'Gerenciar relacionamentos';
$string['relationship:view'] = 'Usar relacionamentos e ver membros';
$string['component'] = 'Fonte';
$string['currentusers'] = 'Usuários atuais';
$string['currentusersmatching'] = 'Usuários atuais que conferem';
$string['delrelationship'] = 'Deletar relacionamento';
$string['delgroupof'] = 'Deletar relacionamento do grupo \'{$a}\'';
$string['delconfirm'] = 'Você realmento quer deletar o relacionamento \'{$a}\'?';
$string['delconfirmgroup'] = 'Você realmente quer deletar o grupo \'{$a}\'?';
$string['description'] = 'Descrição';
$string['duplicateidnumber'] = 'Já há um relacionamento com essa mesma ID';
$string['editgroupof'] = 'Editar relacionamento do grupo \'{$a}\'';
$string['editrelationship'] = 'Editar relacionamento';
$string['event_relationship_created'] = 'Relacionamento criado';
$string['event_relationship_deleted'] = 'Relacionamento deletado';
$string['event_relationship_updated'] = 'Relacionamento atualizado';
$string['event_relationshipgroup_created'] = 'Relacionamento do grupo criado';
$string['event_relationshipgroup_deleted'] = 'Relacionamento do grupo deletado';
$string['event_relationshipgroup_updated'] = 'Relacionamento do grupo atualizado';
$string['event_relationshipgroup_member_added'] = 'Usuários adicionados em um relacionamento';
$string['event_relationshipgroup_member_removed'] = 'Usuários removidos de um relacionamento';
$string['external'] = 'Relacionamento externo';
$string['idnumber'] = 'ID do relacionamento';
$string['memberscount'] = 'Número de membros';
$string['name'] = 'Nome';
$string['groupname'] = 'Nome do Grupo';
$string['groupname_pattern'] = 'Group name pattern';
$string['nocomponent'] = 'Criado manualmente';
$string['potusers'] = 'Potenciais usuários';
$string['potusersmatching'] = 'Possíveis usuários que conferem';
$string['removeuserwarning'] = 'A remoção de usuários de um relacionamento pode resultar no cancelamento da inscrição de usuários em múltiplos cursos o que inclui a deleção de configurações de usuários, notas, participação em grupos e outras informações dos cursos afetados.';
$string['removegroupwarning'] = 'A remoção de grupos de um relacionamento pode resultar no cancelamento da inscrição de usuários em múltiplos cursos o que inclui a deleção de configurações de usuários, notas, participação em grupos e outras informações dos cursos afetados.';
$string['selectfromrelationship'] = 'Selecionar membros do relacionamento';
$string['unknownrelationship'] = 'Relacionamento desconhecido ({$a})!';
$string['useradded'] = 'Usuário adicionado ao relacionamento "{$a}"';
$string['search'] = 'Buscar';
$string['searchrelationship'] = 'Buscar relacionamento: ';
$string['tag'] = 'Etiqueta';
$string['tags'] = 'Etiquetas';
$string['addtag'] = 'Adicionar etiqueta';
$string['relationshiptags'] = 'Lista de etiquetas do relacionamento \'{$a}\'';
$string['edittagof'] = 'Editar etiquetas de \'{$a}\'';
$string['deltagof'] = 'Deletar etiqueta de \'{$a}\''; 
$string['delconfirmtag'] = 'Você realmente quer deletar esta etiqueta \'{$a}\'?';
$string['tagname'] = 'Nome da etiqueta:';
$string['no_delete_tag'] = 'Não é permitido remover etiquetas criadas por outros módulos.';
$string['tag_already_exists'] = 'Esta etiqueta já existe. Entre com outro nome para a etiqueta!';
$string['group_already_exists'] = 'Este grupo já existe. Entre com outro nome para o grupo!';
$string['relationship_already_exists'] = 'Este relacionamento já existe. Entre com outro nome para o relacionamento!';

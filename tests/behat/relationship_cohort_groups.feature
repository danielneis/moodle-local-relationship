@relationship
Feature: Manipulation of cohorts and groups in a relationship
  In order to add, edit or remove cohorts and groups
  I need to have the right capability

Background:
  Given the following "users" exist:
  | username | firstname | lastname | email                 |
  | student1 | Student   |     1    | stundent1@example.com |
  | teacher1 | Teacher   |     1    | teacher1@example.com  |

  And the following "categories" exist:
  | name       | category | idnumber |
  | Category 1 | 0        | CAT1     |

  And the following "courses" exist:
  | fullname | shortname | id | category |
  | Course1  | c1        | 1  | CAT1     |

  And the following "course enrolments" exist:
  | user     | course | role    |
  | student1 | c1     | student |
  | teacher1 | c1     | teacher |

  And the following "permission overrides" exist:
  | capability                | permission | role    | contextlevel | reference |
  | local/relationship:view   | Allow      | teacher | Category     | CAT1      |
  | local/relationship:manage | Allow      | teacher | Category     | CAT1      |
  | local/relationship:assign | Allow      | teacher | Category     | CAT1      |
  | moodle/cohort:view        | Allow      | teacher | Category     | CAT1      |

  And the following "role assigns" exist:
  | user     | role    | contextlevel | reference |
  | teacher1 | teacher | Category     | CAT1      |

  And I log in as "admin"
  And I click on "Course1" "link"
  And I click on "Category 1" "link"
  And I click on "Cohorts" "link"
  And I click on "Add" "button"
  And I fill in "Name" with "Cohort 1"
  And I click on "Save changes" "button"
  And I click on "Add" "button"
  And I fill in "Name" with "Cohort 2"
  And I click on "Save changes" "button"
  And I click on "Assign" "link"
  And I click on the element with xpath "//table/tbody/tr/td[3]/div/select/optgroup/option[1]"
  And I click on "Add" "button"
  And I click on "Relacionamentos" "link"
  And I click on "Add" "button"
  And I fill in "Nome" with "Teste 1"
  And I fill in "Descrição" with "Descrição"
  And I click on "Save changes" "button"
  And I click on "Papeis e coortes" "link"
  And I click on "Add" "button"
  And I click on "Save changes" "button"
  And I log out
  And I log in as "teacher1"
  And I click on "Course1" "link"
  And I click on "Category 1" "link"
  And I click on "Relacionamentos" "link"

@javascript
Scenario: User with capability is able to edit a cohort in a relationship
  When I click on "Papeis e coortes" "link"
  Then I should see "No" in the "//table[@id='relationships']/tbody//td[4]" "xpath_element"
  When I click on "Edit" "link"
  Then I should see "Editar papel/coorte"
  When I select "Yes" from "Inscrição em vários grupos"
  And I click on "Save changes" "button"
  Then I should see "Yes" in the "//table[@id='relationships']/tbody//td[4]" "xpath_element"

@javascript
Scenario: User with capability is able to delete cohort from a relationship
  When I click on "Papeis e coortes" "link"
  Then I should see "Cohort 1" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"
  When I click on "Delete" "link"
  And I click on "Continue" "button"
  Then I should not see "Cohort 1"

@javascript
Scenario: User with capability is able to create groups in a relationship
  When I click on "Groups" "link"
  Then I should see "" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"
  When I click on "Adicionar novo grupo" "button"
  And I fill in "Nome do Grupo" with "Grupo teste"
  And I click on "Save changes" "button"
  Then I should see "Grupo teste" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"

@javascript
Scenario: User with capability is able to edit groups in a relationship
  When I click on "Groups" "link"
  And I click on "Adicionar novo grupo" "button"
  And I fill in "Nome do Grupo" with "Grupo teste"
  And I click on "Save changes" "button"
  Then I should see "Grupo teste" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"
  When I click on "Edit" "link"
  Then I should see "Editar grupo"
  When I fill in "Nome do Grupo" with "Teste"
  And I click on "Save changes" "button"
  Then I should see "Teste" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"

@javascript
Scenario: User with capability is able to remove groups from a relationship
  When I click on "Groups" "link"
  And I click on "Adicionar novo grupo" "button"
  And I fill in "Nome do Grupo" with "Grupo teste"
  And I click on "Save changes" "button"
  Then I should see "Grupo teste" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"
  When I click on "Delete" "link"
  And I click on "Continue" "button"
  Then I should see "" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"

@javascript
Scenario: Changes made to groups are shown to user
  When I click on "Groups" "link"
  And I click on "Adicionar novo grupo" "button"
  And I fill in "Nome do Grupo" with "Grupo teste"
  And I click on "Save changes" "button"
  Then I should see "No" in the "//table[@id='relationships']/tbody//td[4]" "xpath_element"
  When I click on "ativar" "link"
  Then I should see "Yes" in the "//table[@id='relationships']/tbody//td[4]" "xpath_element"


@javascript
Scenario: Removing a cohort with an enrolled user
  When I click on "Papeis e coortes" "link"
  Then I should not see "Cohort 2"
  When I click on "Add" "button"
  And I click on "Save changes" "button"
  Then I should see "Cohort 2"
  When I click on "Relacionamentos" "link"
  And I click on "Groups" "link"
  And I click on "Adicionar novo grupo" "button"
  And I fill in "Nome" with "Grupo 1"
  And I click on "Save changes" "button"
  And I click on "Atribuir" "link"
  And I click on "//table/tbody/tr/td[3]/div/select/optgroup/option[1]" "xpath_element"
  And I click on "Add" "button"
  And I click on "Grupos" "link"
  Then I should see "1" in the "//table/tbody/tr[1]/td[2]" "xpath_element"
  When I click on "Relacionamentos" "link"
  And I click on "Papeis e coortes" "link"
  And I click on "//table/tbody/tr[2]/td[6]/a" "xpath_element"
  And I click on "Continue" "button"
  And I click on "Relacionamentos" "link"
  And I click on "Groups" "link"
  Then I should see "0" in the "//table/tbody/tr/td[2]" "xpath_element"

@javascript
Scenario: Removing a relationship with an associated cohort
  When I click on "delete" "link"
  And I click on "Continue" "button"
  Then I should see "O relacionamento não pode ser excluído pois há um ou mais coortes cadastrados"

@javascript @wip
Scenario: Editing a relationship's parameters
  Then I should see "Teste 1"
  And I should not see "Novo nome"
  When I click on "Edit" "link"
  And I fill in "Nome" with "Novo nome"
  And I click on "Save changes" "button"
  Then I should see "Novo nome"
  And I should not see "Teste 1"

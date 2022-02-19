@report @report_advancedgrading

Feature: Display the advanced grading report
  Background:
    Given the following config values are set as admin:
        | import_general_duplicate_admin_allowed | 1 | backup |
    Given the following "courses" exist:
        | fullname        | shortname | category |
        | Course1         | C1        | 0        |
        | AdvancedGrading | AG        | 0        |
    Given the following "users" exist:
        | username | firstname | lastname | email          |
        | s1       | student   | user1    | s1@example.com |
    And the following "course enrolments" exist:
        | user | course | role    |
        | s1   | AG     | student |
    And I log in as "admin"
  @javascript @_file_upload
  Scenario: do this thing
    When I am on "Course1" course homepage
    And I navigate to "Restore" in current page administration
    And I press "Manage backup files"
    And I upload "report/advancedgrading/tests/fixtures/backup-advgrade.mbz" file to "Files" filemanager
    And I press "Save changes"
    And I restore "backup-advgrade.mbz" backup into "AdvancedGrading" course using this options:
    And I log out
    And I log in as "s1"
    And I am on "AdvancedGrading" course homepage
    And I click on "Rubric with multiple attempts" "link"
    And I click on "Add submission" "button"
    And I set the field "Online text" to "First response"
    And I click on "Save changes" "button"
    And I log out
    And I log in as "admin"
    And I am on "AdvancedGrading" course homepage
    And I click on "Rubric with multiple attempts" "link"
    And I navigate to "Rubric breakdown report" in current page administration
    And I should see "No marked submissions found"
    And I am on "AdvancedGrading" course homepage
    And I go to "s1" "Rubric with multiple attempts" activity advanced grading page
    And I navigate to "Rubric breakdown report" in current page administration
    And I should see "Rubric"
    # And I should see "Score"
    # And I pause
    # And I should see "75" in the "s1" "table_row"
    # And I log out
    # And I log in as "admin"
    # When I am on "Advanced Grading 101" course homepage
    # And I click on "Marking Guide" "link"
    # And I navigate to "Marking guide breakdown report" in current page administration
    # And I should see "Marking guide"
    # And I should see "Score"
    # And I should see "No marked submissions found"
    #And I should see "Approved" in the "Victim User 1" "table_row"


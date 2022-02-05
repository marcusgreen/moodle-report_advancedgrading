@report_advancedgrading

Feature: Display the advanced grading report
  Background:
    Given the following config values are set as admin:
        | import_general_duplicate_admin_allowed | 1 | backup |
    Given the following "courses" exist:
        | fullname | shortname | category |
        | Course 1 | C1        | 0        |

    And I log in as "admin"
  @javascript @_file_upload
  Scenario: do this thing
    When I am on "Course 1" course homepage
    And I navigate to "Restore" in current page administration
    And I press "Manage backup files"
    And I upload "report/advancedgrading/tests/fixtures/backup-advgrade.mbz" file to "Files" filemanager
    And I press "Save changes"
    And I restore "backup-advgrade.mbz" backup into a new course using this options:
    And I click on "Rubric assignment" "link"
    And I navigate to "Rubric breakdown report" in current page administration
    And I should see "Rubric"
    And I should see "Score"
    And I log out
    And I log in as "admin"
    When I am on "Advanced Grading 101" course homepage
    And I click on "Marking Guide" "link"
    And I navigate to "Marking guide breakdown report" in current page administration
    # And I should see "Marking guide"
    # And I should see "Score"
    # And I should see "No marked submissions found"

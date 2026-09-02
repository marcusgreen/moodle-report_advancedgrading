@report @report_advancedgrading @report_advancedgrading_rubric_workflow @javascript

Feature: Advancedgrading rubric report shows allocated marker and marking workflow state
  In order to monitor who is marking what and where each submission sits in the workflow
  As an editing teacher viewing the rubric breakdown report
  Then the report shows the allocated marker and the localised marking workflow state

  Background:
    Given the following config values are set as admin:
      | enable_javascriptlayout | 0 | report_advancedgrading |

  Scenario: Allocate a marker, set workflow state, and verify the breakdown report.
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | teacher2 | Teacher   | 2        | teacher2@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activity" exists:
      | activity                            | assign            |
      | name                                | Test assignment 1 |
      | course                              | C1                |
      | section                             | 1                 |
      | assignsubmission_file_enabled       | 0                 |
      | assignsubmission_onlinetext_enabled | 1                 |
      | attemptreopenmethod                 | manual            |
      | assignfeedback_comments_enabled     | 1                 |
      | markingworkflow                     | 1                 |
      | markingallocation                   | 1                 |
    And I am on the "Test assignment 1" "assign activity editing" page logged in as teacher1
    And I set the following fields to these values:
      | Grading method | Rubric |
    And I press "Save and return to course"
    # Defining a rubric
    When I go to "Test assignment 1" advanced grading definition page
    And I change window size to "large"
    And I set the following fields to these values:
      | Name        | Assignment 1 rubric     |
      | Description | Rubric test description |
    And I define the following rubric:
      | Criterion 1 | Good writing      | 10 | Excellent writing   | 30 |
      | Criterion 2 | OK Punctuation    | 20 | Perfect Punctuation | 35 |
      | Criterion 3 | Slightly original | 15 | Truly original      | 40 |
    And I press "Save rubric and make it ready"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on "Test assignment 1" "link"
    And I click on "Add submission" "button"
    And I set the field "Online text" to "First response"
    And I click on "Save changes" "button"
    And I log out
    # teacher1 grades and allocates teacher2 as the marker
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I go to "Student 1" "Test assignment 1" activity advanced grading page
    And I grade by filling the rubric with:
      | Criterion 1 | 10 | Nice       |
      | Criterion 2 | 20 | Good       |
      | Criterion 3 | 15 | Acceptable |
    And I set the field "Marking workflow state" to "In marking"
    And I set the field "allocatedmarker" to "Teacher 2"
    And I press "Save changes"
    And I complete the advanced grading form with these values:
      | Feedback comments | In general... work harder... |
    And I am on the "Test assignment 1" "assign activity" page
    And I navigate to "Rubric breakdown report" in current page administration
    And I wait until the page is ready
    # The new marker and workflow columns and their values are visible
    And I should see "Marker"
    And I should see "Marking workflow state"
    And I should see "Teacher 2"
    And I should see "In marking"
    # Move the workflow state on and confirm the column updates
    When I go to "Student 1" "Test assignment 1" activity advanced grading page
    And I set the field "Marking workflow state" to "Released"
    And I press "Save changes"
    And I am on the "Test assignment 1" "assign activity" page
    And I navigate to "Rubric breakdown report" in current page administration
    And I wait until the page is ready
    And I should see "Released"
    And I should see "Teacher 2"

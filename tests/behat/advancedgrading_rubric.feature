@report @report_advancedgrading @report_advancedgrading_rubric   @javascript
Feature: Confirm advancedgrading report works for multiple submission of rubric
    In order to view multiple submissions of rubric
    As a teacher view the advanced grading report
  Background:
    Given the following config values are set as admin:
        | enable_javascriptlayout | 0 | report_advancedgrading |
  Scenario: Convert rubric scores to grades.
    Given the following "users" exist:
        | username | firstname | lastname | email                |
        | teacher1 | Teacher   | 1        | teacher1@example.com |
        | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
        | fullname | shortname | format |
        | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
        | user     | course | role           |
        | teacher1 | C1     | editingteacher |
        | student1 | C1     | student        |
    Given the following "activity" exists:
        | activity                            | assign            |
        | name                                | Test assignment 1 |
        | course                              | C1                |
        | section                             | 1                 |
        | assignsubmission_file_enabled       | 0                 |
        | assignsubmission_onlinetext_enabled | 1                 |
        | attemptreopenmethod                 | manual            |
        | blindmarking                        | 1                 |
        | assignfeedback_comments_enabled     | 1                 |

    And I am on the "Test assignment 1" "assign activity editing" page logged in as teacher1
    And I set the following fields to these values:
        | Grading method | Rubric |
    And I press "Save and return to course"
        # Defining a marking guide
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

    And I log in as "teacher1"
    # And I am on "Course 1" course homepage with editing mode on

    # # And I go to "Student 1" "Test assignment 1" activity advanced grading page
    # And I grade by filling the rubric with:
    #     | Criterion 1 | 10 | Nice       |
    #     | Criterion 2 | 20 | Good       |
    #     | Criterion 3 | 15 | Acceptable |
    # And I press "Save changes"
    # And I complete the advanced grading form with these values:
    #     | Feedback comments | In general... work harder... |
    # And I log out
    # And I log in as "teacher1"

    # And I am on "Course 1" course homepage with editing mode on
    # And I am on the "Test assignment 1" "assign activity" page
    # And I navigate to "Rubric breakdown report" in current page administration

    # And I go to "Student 1" "Test assignment 1" activity advanced grading page
    # And I set the following fields to these values:
    #     | Allow another attempt | Yes |
    # And I save the advanced grading form

    # And I log out
    # And I log in as "student1"
    # And I am on "Course 1" course homepage
    # And I click on "Test assignment 1" "link"

    # And I click on "Add a new attempt" "button"
    # And I set the field "Online text" to "Second response"
    # And I click on "Save changes" "button"
    # And I log out

    # And I log in as "teacher1"
    # And I am on "Course 1" course homepage with editing mode on

    # And I go to "Student 1" "Test assignment 1" activity advanced grading page
    # And I grade by filling the rubric with:
    #     | Criterion 1 | 30 | Terrific    |
    #     | Criterion 2 | 35 | Good        |
    #     | Criterion 3 | 40 | Much better |
    # And I complete the advanced grading form with these values:
    #     | Feedback comments | A massive improvement, well done you... |
    # And I am on "Course 1" course homepage with editing mode on
    # And I am on the "Test assignment 1" "assign activity" page
    # And I navigate to "Rubric breakdown report" in current page administration

    # And I am on "Course 1" course homepage
    # And I follow "Test assignment 1"
    # And I navigate to "Rubric breakdown report" in current page administration

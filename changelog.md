### Version 1.04 Oct 2025
Confirmed compatibility with Moodle 5.1.

Exported files (Excel or CSV) now have a name that combines the course name and the assignment name. So an assignment called Quadratic1 on a course Maths101 would be exported as Maths101-Quadratic1.xlsx

Thanks to  Dragos Suciu for suggesting this feature
https://github.com/marcusgreen/moodle-report_advancedgrading/issues/45

### Version 1.03 JUly 2025
Add a separate advancedgrading/view capability, so people can be granted the ability to view without being able to edit.
Useful for external examiners/moderators. With thanks to Jordi Pujol-Ahulló for the idea, the code, testing and all round positive attitude.


### Version 1.02 April 2025
Confirmed compatibility with Moodle 5.0

Blind marking was not being applied when processing for a BTEC grading
This was noticed during a scan using OpenAI 04-mini.

Thanks to Juan Segarra Montesinos for code to fix
https://github.com/marcusgreen/moodle-report_advancedgrading/issues/19
To ensure only active grading instances should be shown

Thanks to Dan Marsden of Catalyst NZ for reporting
https://github.com/marcusgreen/moodle-report_advancedgrading/issues/24
Where an image in feedback caused downloads to fail. Also thanks to
Dragos Suciu for reminding me. It is now addressed through a preg_replace
in the download function.

### Version 1.01 Oct 2024
Confirmed compatibility with Moodle 4.5 and PHP 8.3
Thanks to Kevin Hipwell  and Enovation for contribution to rubref (rubric refined also known as rubric flex)
https://github.com/marcusgreen/moodle-report_advancedgrading/issues/30

### Version 1.0 May 2024

Added behat test compatibility to work with Moodle 4.4. Thanks to Monash university Australia and thanks to Dmitrii Metelkin of
Catalyst AU for code to support https://github.com/catalyst/moodle-gradingform_rubric_ranges.
Added support for rubric_flex/refined. Thanks to Michael Aherne for code to conditionally run unit tests.

### Version 0.91 Nov 2023
Added BTEC grading to the type of advanced grading supported.

Changed source of grader (person doing marking) from grading_instances table to
assignment grade. This fixes the issue that the grader was recorded as the last
person who scrolled through the grading interface got marked as the grader. Made the
change for Rubric, Marking Guid and BTEC,

### Version 0.9 Jul 2023
Remove all reference to datatables javascript modules as per
https://github.com/marcusgreen/moodle-report_advancedgrading/issues/9
Datatables was being used for sorting and formatting but it broke javascript site wide.
Many thanks to Iñigo Zendegi Urzelai

localisation of header fields as per
https://github.com/marcusgreen/moodle-report_advancedgrading/issues/6.
Solution from https://github.com/moodleulpgc
Many thanks to Enrique Castro

Put report page in module context not course context so the navigation
makes sense. See issue
https://github.com/marcusgreen/moodle-report_advancedgrading/issues/10.
Again, many thanks to Enrique Castro

Change layout to report to make better use of the screen

Added BTEC marking as an advanced grading type that it can report on.
https://docs.moodle.org/402/en/BTEC_marking


### Version 0.2 Oct 2022
Removed spurious % in time graded output.
Thanks to Vlad Kidanov for reporting the alphabet generation would stop at 26 and contributing
code to make it dependent on column count.

### Version 0.1 Feb 2022
First release

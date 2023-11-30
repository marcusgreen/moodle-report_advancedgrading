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

=== Exam Hall ===
Contributors: examhall
Tags: exam, quiz, students, grading, education
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Timed exams with student accounts, emailed start codes, CSV questions, and automatic grading.

== Description ==

Exam Hall is an examination plugin for WordPress.

Administrators can:

* Capture student details and email a temporary password
* Reset a student password by email
* Set the exam duration, open and close times, pass mark, and classification bands
* Choose a multiple choice or true or false exam, and set the marks for each question
* Download a CSV template, then upload questions and the correct answers
* Upload students from a CSV, using the downloadable student template
* See percentages, classifications, and each answer
* Add a company logo and name across the portal and dashboard
* Allocate an exam to selected students, including a bulk selection
* Upload a PNG certificate design and position the name, subject, grade, and date

Students can:

* Sign in with email and password
* Reset a forgotten password from their email address
* Request a start code, which is emailed before the exam begins
* Move previous and next through the paper, with a progress bar and countdown
* See their percentage, classification, and whether they passed
* Retake the exam when the administrator allows it

== Installation ==

1. Upload the `exam-hall` folder to `/wp-content/plugins/`, or upload the zip from Plugins → Add New → Upload Plugin.
2. Activate Exam Hall.
3. Open Exam Hall in the WordPress admin.
4. Add a student, publish an exam, and upload questions.
5. Students use the Exam Hall page created on activation.

WordPress must be able to send email. Install an SMTP plugin if start codes or password resets do not arrive. Administrators can also read a pending start code from the Exam Hall dashboard.

== Frequently Asked Questions ==

= What CSV columns are required for questions? =

Multiple choice: question, option_a, option_b, option_c, option_d, correct_answer, marks.

correct_answer can be A, B, C, D, or the exact text of an option.

True or false: question, correct_answer, marks.

correct_answer can be True or False. Marks are optional. A blank marks cell uses the marks set for that exam. Download the template from the question screen.

= What CSV columns are required for students? =

first_name, last_name, email, student_number, phone, programme, notes, status.

phone, programme, notes, and status can be blank. status is active or suspended. Download the template from the Students screen.

= When does the timer start? =

After the student enters the start code that was emailed to them.

= What are the default classifications? =

80–100 Distinction, 70–79 Merit, 50–69 Pass, and below 50 Fail. Each exam can use its own thresholds and labels.

== Changelog ==

= 1.2.0 =
* Download a CSV template for multiple choice or true or false questions, then upload it for automatic grading.
* Set the marks for each question on the exam, and override them per row.
* Upload students in bulk from a CSV, with a downloadable template.

= 1.1.0 =
* Company logo and name, exam allocation, and PNG certificates.

= 1.0.0 =
* Initial release.

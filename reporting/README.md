# JasperStarter runtime

## Naming generated PDFs

The Run System Report form has an optional **PDF Name** field. A name such as
`October-December Calendar` saves and downloads as `October-December Calendar.pdf`
and appears in the generated-report list. It does not change the report's printed
heading. A trailing `.pdf` is accepted and not duplicated. Names support spaces
and Unicode, up to 100 characters and 200 bytes; filename separators, control
characters, and reserved device names are rejected.

Leaving the field blank preserves `<report_key>_<YYYYmmdd>_<His>.pdf`. Existing
PDFs are never overwritten: duplicate custom names become `Name (2).pdf`,
`Name (3).pdf`, and so on. No database migration is required.

## Calendar formatting

### 12-hour times

The shared calendar procedure formats start/end times as `h:mm AM/PM` for
the public calendar, members' calendar, and PDF calendar reports. Midnight is
`12:00 AM` and noon is `12:00 PM`; overnight entries retain their date labels.
The web calendar also supports older 24-hour procedure output without adding
AM/PM twice to already-formatted times.

Deploy `calendar.php` and `config/mysql/calendar_build_range.sql`, then run
the following from the repository root using your normal database credentials
(after the program-weekdays migration below):

```sh
mysql nbbtm_central < config/migrations/2026-10-10_calendar_12_hour_times.sql
```

Changing the SQL file alone does not update the installed stored procedure.
No JRXML/Jasper recompilation is required. Generate a new PDF to see the updated
times; previously saved PDFs do not change.

### Program weekdays and date exceptions

In **Admin > Calendar Schedule > Program / Event Calendar Days**, select a saved
program activity, choose **Selected weekdays only**, check its weekdays (for
example, Sunday only), and save. The activity's start/end dates from Event
Management define an inclusive recurrence range; its start/end times apply to
each occurrence. Equal times produce a point entry, an earlier end time runs
overnight, and no end date means only the start date is eligible.

Use **Add Program Exception** to cancel or reschedule one selected occurrence.
The replacement may fall outside the original range or on another weekday.
Deleting an exception restores the original occurrence. Calendar days apply to
both signed-in/public calendars and calendar reports, respecting the event's
existing public/private setting. Registration and other event details are not
changed. Overnight occurrences can also appear on their following day.

Existing and new activities default to **Continuous date range**, preserving
the previous display. Activity IDs, weekdays, and exceptions survive ordinary
event edits. Removing an activity removes its exceptions. Changes to weekdays
or date ranges that would orphan an exception are rejected; edit/delete those
exceptions first.

Before deploying the updated PHP files, run this migration **once** from the
repository root (using your normal database credentials):

```sh
mysql nbbtm_central < config/migrations/2026-10-08_program_calendar_weekdays.sql
```

The migration adds the weekday column and exception table, allows the activity
end to be blank as offered by Event Management, and sources the
updated `config/mysql/calendar_build_range.sql`. Deploy that SQL file with the
migration. It leaves existing activity data unchanged. Deploy `events.php`,
`prg_event_api.php`, `calendar_schedule.php`, `calendar_schedule_api.php`, and
`include/program_calendar.php` together. Reload already-open Event Management
forms before saving. No JRXML/Jasper recompilation is required for this change.

Calendar stored procedures may return Jasper styled-text tags such as
`<style forecolor="...">`. The app calendar maps report colors to entry types
(Program `#2E7D32`, Service `#6A1B9A`, Reminder `#EF6C00`, Birthday `#C2185B`,
Anniversary `#00838F`) to preserve its existing background colors without visible
type prefixes. It removes the tags, decodes Jasper's escaped title text, and
still HTML-escapes the displayed text. Plain entries with type prefixes remain
supported. This display-only conversion leaves the stored procedure output and
PDF report formatting unchanged. Keep the mapping in `calendar.php` synchronized
if the report colors change.

All seven entry fields in `three_month_calendar.jrxml` use `markup="styled"` so
the PDF renders these tags as colored text rather than printing them literally.
Recompile `three_month_calendar.jasper` after changing the template and deploy
both files; PDF generation uses the compiled report.

The calendar layout is adapted from the Jaspersoft Studio workspace's
JasperReports 7 template into the application's JasperReports 6 JRXML format.
It retains Liberation Serif, blue headings, the header image, and the colored
footer legend. Do not deploy the Studio 7 compiled `.jasper` directly to this
runtime. The calendar declares `IMAGE_DIR`; the generator supplies the absolute
application `images/` directory on the server and checks that
`nbbtm header - no addr.png` is readable. The image does not rely on classpath
lookup or a hardcoded deployment path. No report-parameter database change is
required. Deploy the updated generator, JRXML, compiled `.jasper`, and header
image together.

Report generation and compilation use the executable `bin/jasperstarter` PHP
launcher. Keep its executable permission when deploying (`chmod +x
reporting/bin/jasperstarter`).

`JASPER_STARTER_PATH=storage/jasperstarter/bin/jasperstarter` in the root `.env`
points to the Java 21-compatible JasperStarter installation. The application
launcher locates `../lib/jasperstarter.jar` relative to that executable.
`REPORTS_FONTS_PATH` points to the directory containing custom font-extension
JARs. Fonts, styles, libraries, and JDBC drivers are included on Java's classpath
for both compilation and PDF export. The launcher opens `java.net` to unnamed
modules because this upstream build still uses reflection to add resources.
Report styles also continue to use the separate resources option.

The launcher uses `$JAVA_HOME/bin/java` when `JAVA_HOME` is set, then
`$JASPER_JAVA_HOME/bin/java`, otherwise `java` from `PATH`. Both Java home
settings can be loaded from the root `.env` file or the process environment.
This installation is tested with Java 21 and keeps JasperReports 6.18.1 for
compatibility with existing report templates.
Do not configure `-Djava.ext.dirs` in
`JAVA_TOOL_OPTIONS`: Java 9 and later no longer support extension directories.

## Installing the runtime

The Composer-bundled JasperStarter 3.6.2 is not compatible with Java 21's
class loader. Build the official upstream Java 9+ branch, pinned to commit
`596eced96dfc91910c0ad4bba12d0877eb679ae2` (3.7.0-SNAPSHOT):

```sh
git clone --branch jas-98_Java_9plus_Issues_with_URLClassLoader \
  https://bitbucket.org/cenote/jasperstarter.git /tmp/nbbtm-jasperstarter-build
git -C /tmp/nbbtm-jasperstarter-build checkout --detach \
  596eced96dfc91910c0ad4bba12d0877eb679ae2
mvn -B -ntp -f /tmp/nbbtm-jasperstarter-build/pom.xml -DskipTests package
tar -xjf /tmp/nbbtm-jasperstarter-build/target/jasperstarter-3.7.0-SNAPSHOT-bin.tar.bz2 \
  -C storage
cp vendor/geekcom/phpjasper/bin/jasperstarter/jdbc/*.jar storage/jasperstarter/jdbc/
chmod +x reporting/bin/jasperstarter storage/jasperstarter/bin/jasperstarter
```

Run from the application root with Git, Maven, and Java 21 available. The
upstream build tests are skipped because they include environment-dependent
integration tests; verify report compilation and PDF export locally afterward.
The generated runtime is ignored by Git and must be installed on each deployment.
Keep its upstream `LICENSE` and `NOTICE` files. Composer's files are not modified.

To check startup without accessing the database:

```sh
reporting/bin/jasperstarter --version
```

## Compiling from the application

Admins and developers can open **Admin > Reports > Compile Reports**, select a
saved JRXML template, and click **Compile Report**. The button shows
**Compiling...** while the request runs and prevents duplicate submissions.
The page then displays either the compilation confirmation or the compiler's
error details. Compilation still works without JavaScript; progress feedback
requires JavaScript. A template must be selected before submitting.

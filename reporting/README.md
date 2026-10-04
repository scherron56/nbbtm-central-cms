# JasperStarter runtime

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

The launcher uses `$JAVA_HOME/bin/java` when `JAVA_HOME` is set, otherwise
`java` from `PATH`. This installation is tested with Java 21 and keeps
JasperReports 6.18.1 for compatibility with existing report templates.
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

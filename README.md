# GeoGebraBook2LaTeX

This program can convert a GeoGebra book, given in HTML format and as a
set of images, into LaTeX, and then into PDF.

GeoGebra books can be created at https://www.geogebra.org by downloading
a .zip file. To do that,

1. select the material you want (e.g. https://www.geogebra.org/m/Hbp85Avk),
2. go to the kebap menu (on the top-right), click Details,
3. on the right, click Download,
4. accept GeoGebra's non-commercial license,
5. download the offline book in .zip format,
6. extract the .zip file somewhere.

At this point you will get a large .html file and a folder `GeoGebra` with
several files, including the web version of GeoGebra and all materials
included in the book, also additional artwork and screenshots.

Now, if you want to convert the book into LaTeX, you need to start
the script `HTML2LaTeX-cl.php` on a system where you have the following
software already installed (this list is contains the names of the
Ubuntu 22.04 Linux packages, for reference):

* `php-cli`
* `php-gd`
* `php-mbstring`
* `php-xml` (formerly, this package has the name `php-dom`)
* `texlive-latex-base`
* `texlive-latex-extra`
* `texlive-latex-recommended`
* `zip`

The simplest way to install these packages is to use a Linux system,
but these pieces of software are available for all major platforms.

To run the script, you need to type `php HTML2LaTeX-cl.php FULL-FILENAME-OF-THE-GEOGEBRA-BOOK.html`,
then wait a couple of seconds, and hopefully you get the output as `FULL-FILENAME-OF-THE-GEOGEBRA-BOOK.tex`,
`FULL-FILENAME-OF-THE-GEOGEBRA-BOOK.tex`, and `FULL-FILENAME-OF-THE-GEOGEBRA-BOOK.zip`.
In this last file there will be put a folder `images` with the extracted images
from the GeoGebra book (this folder will be saved in the working directory
as well).

If you are satisfied with the output, that's fine! If not, you may do some
fine-tuning in the scripts. Another option is to fine-tune the LaTeX output (.tex),
and then re-issue the command `pdflatex FULL-FILENAME-OF-THE-GEOGEBRA-BOOK.tex`.

Good luck!

## Disclaimer

The script works well in many situations, but certainly not always.
Feedback and suggestions for improvements are welcome.

## Author

Zoltán Kovács (zoltan@geogebra.org)

## License

GPL 3

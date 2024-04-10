<?php

// Class for creating LaTeX version of an HTML offline GeoGebraBook.
// Author: Zoltan Kovacs <zoltan@geogebra.org>
// Usage: See HTML2LaTeX-cl.php for details.

class HTML2LaTeX
{

    private $working_dir = ".", $images_dir = "images", $language = "english", $language_babel = "english", $verbosity = 0, $view = NULL;

    // These should be put once into the configurable set of options.
    private $MAXWIDTH_MM = 120;   // image maximal width
    private $MINWIDTH_MM = 30;    // image minimal width
    private $MAXHEIGHT_INLINE_MM = 5; // image maximal height for inline images
    private $MINHEIGHT_INLINE_MM = 4; // image minimal height for inline images
    private $IMAGE_RATIO = 5;     // image reduction factor
    private $INLINE_IMG_SCALE = 0.5; // inline image magnification factor
    private $INLINE_ICON_SIZE = 0.7; // inline icon size (in centimeters)
    private $MAX_COLUMN_WIDTH = 0.8; // for tables (see "tabulary" package)
    private $IMAGE_CACHED = TRUE; // don't recreate images if they are already created
    private $TEXTCOLOR = TRUE;    // show colors (or only italic text)
    private $REFERENCES = TRUE;   // produce bibliography
    private $NOINDENT = TRUE;     // Don't indent non-first paragraph (maybe not useful for scientific documents)

    // These should not be externally changed.
    private $WORDWRAP = FALSE;    // wrap text elements in output (may break hrefs, not recommended)

    protected $imgno = 0;

    public function setWorkingDir($dir)
    {
        $working_dir = $dir;
    }

    public function setImageDir($dir)
    {
        $this->images_dir = $dir; // helper directory (must also be bundled)
    }

    // sets language name (e.g. "german") for \selectlanguage
    public function setLanguage($lang)
    {
        $this->language = $lang;
    }

    // sets babel language name (e.g. "ngerman")
    public function setLanguageBabel($lang)
    {
        $this->language_babel = $lang;
    }

    // sets 2 letters language code (e.g. "de")
    public function setISO639Language($lang)
    {
        $this->language = $this->iso2babel($lang);
        $this->language_babel = $this->iso2babel($lang, false);
    }

    public function setVerbosity($v)
    {
        $this->verbosity = $v;
    }

    // injects the Controller -> View object to make it possible to get the translation texts
    public function setView($v)
    {
        $this->view = $v;
    }

    public function getWorkingDir()
    {
        return $this->working_dir;
    }

    public function getImageDir()
    {
        return $this->images_dir;
    }

    public function getLanguage()
    {
        return $this->language;
    }

    public function getLanguageBabel()
    {
        return $this->language_babel;
    }

    /* Create a .tex file and some images in the images_dir folder.
     * Then create a .pdf from them by running pdflatex.
     * Note: You will need most of the LaTeX engine to make this work.
     * We assume that the pdflatex utility is on the path (which is the
     * default on a Linux system if Apache is not in a jail).
     */
    public function createPDF($inputfile)
    {
        $info = pathinfo($inputfile);
        $base = $info['filename'];
        $texfile = $base . ".tex";
        $pdffile = $base . ".pdf";
        $dir = getcwd();
        chdir($this->working_dir);

        // Creating .tex file
        $texf = fopen($texfile, "w");
        fwrite($texf, $this->createLaTeXStream($inputfile));
        fclose($texf);

        // Creating .pdf file
        passthru("echo q | pdflatex $base > pdflatex-run1.out 2> pdflatex-run1.err");
        passthru("echo q | pdflatex $base > pdflatex-run2.out 2> pdflatex-run2.err");

        chdir($dir);
    }


    /* Create a .zip bundle containing the .tex and .pdf files and also the images.
     * From GeoGebraTube you will not use this function, but from an own application,
     * for example from HTML2LaTeX-cl.php, you may.
     */
    public function createZip($inputfile)
    {
        $this->createPDF($inputfile);
        $info = pathinfo($inputfile);
        $base = $info['filename'];
        $texfile = $base . ".tex";
        $pdffile = $base . ".pdf";
        $zipfile = $base . ".zip";
        $dir = getcwd();
        chdir($this->working_dir);
        $command = "zip -r $zipfile $pdffile $texfile " . $this->images_dir;
        passthru($command);
        chdir($dir);
    }

    /* Return a list of files to be bundled in a .zip file.
     * Pdf.php directly uses this.
     */
    public function getFileList($inputfile)
    {
        $info = pathinfo($inputfile);
        $base = $info['filename'];
        $dir = $info['dirname'];
        $texfile = $base . ".tex";
        $pdffile = $base . ".pdf";
        $files = array();
        $files[] = array(
            PCLZIP_ATT_FILE_NAME => $dir . '/' . $texfile,
            PCLZIP_ATT_FILE_NEW_FULL_NAME => $texfile);
        $files[] = array(
            PCLZIP_ATT_FILE_NAME => $dir . '/' . $pdffile,
            PCLZIP_ATT_FILE_NEW_FULL_NAME => $pdffile);
        if (file_exists($dir . '/' . $this->images_dir)) {
            $files[] = array(
                PCLZIP_ATT_FILE_NAME => $dir . '/' . $this->images_dir,
                PCLZIP_ATT_FILE_NEW_FULL_NAME => $this->images_dir);
        }
        return $files;
    }

    private function entex($text)
    {
        // Turning problematic texts to TeX compliant ones.
        // They need to be undone later for some constructs, however, by using detex().
        $text = str_replace("\\", "\\textbackslash{}", $text);

        $text = str_replace("_", "\\_", $text);
        $text = str_replace('$', "\\$", $text);
        $text = str_replace("%", "\\%", $text);
        $text = str_replace("&", "\\&", $text);
        $text = str_replace("{", "\\{", $text);
        $text = str_replace("}", "\\}", $text);
        $text = str_replace("~", "\\textasciitilde{}", $text);
        $text = str_replace("^", "\\string^", $text);
        $text = str_replace(">", "\\textgreater{}", $text);
        $text = str_replace("<", "\\textless{}", $text);
        return $text;
    }

    private function detex($text)
    {
        // The inverse of entex().
        $text = str_replace("\\textgreater{}", ">", $text);
        $text = str_replace("\\textless{}", "<", $text);
        $text = str_replace("\\textasciitilde{}", "~", $text);
        $text = str_replace("\\string^", "^", $text);
        $text = str_replace("\\_", "_", $text);
        $text = str_replace('\$', "$", $text);
        $text = str_replace("\\%", "%", $text);
        $text = str_replace("\\&", "&", $text);
        $text = str_replace("\\{", "{", $text);
        $text = str_replace("\\}", "}", $text);

        $text = str_replace("\\textbackslash{}", "\\", $text);
        return $text;
    }

    // Taken from http://php.net/manual/en/function.urlencode.php
    function myUrlEncode($string) {
        $entities = array('%21', '%2A', '%27', '%28', '%29', '%3B', '%3A', '%40', '%26', '%3D', '%2B', '%24', '%2C', '%2F', '%3F', '%25', '%23', '%5B', '%5D');
        $replacements = array('!', '*', "'", "(", ")", ";", ":", "@", "&", "=", "+", "$", ",", "/", "?", "%", "#", "[", "]");
        return str_replace($entities, $replacements, urlencode($string));
    }

    private function remove_whitespaces_bb($text)
    {
        $text = str_replace("[br]", " ", $text);
        return $this->remove_whitespaces($text);
    }

    private function remove_whitespaces($text)
    {
        $text = str_replace("\n", " ", $text);
        $text = trim($text);
        $text = preg_replace("/\s\s+/", " ", $text);
        return $text;
    }

    private function cell_newlines($text)
    {
        $text = trim($text);
        // Replace multiple (one ore more) line breaks with a single one.
        $text = preg_replace("/[\r\n]+/", "\n", $text);
        $text = str_replace("\n", "{\\newline}", $text);
        return $text;
    }

// Convert a HTML to a LaTeX one.
// This is quite dirty. There should be a better way by using an own parser here,
// which is much faster and more general. Here we handle *most* situations, but certainly not all.
    private function textprocessor_start($text)
    {
        return $this->textprocessor($this->entex($text));
    }

    private function preg_replace_multiple($pattern, $replacement, $text) {
        // return preg_replace($pattern, $replacement, $text);
        $doit = TRUE;
        while ($doit) {
            $this->verbose("preg_replace_multiple: $pattern", 2);
            $text = preg_replace($pattern, $replacement, $text, -1, $count);
            $doit = $count > 0;
        }
        return $text;
    }

    private function textprocessor($text)
    {

        $this->verbose("textprocessor(..." . strlen($text) . "...)", 2);

        // remove 0xfffe
        $bom = pack('H*','EFBBBF');
        $text = preg_replace("/$bom/", '', $text);

        // math
        $text = preg_replace_callback("/\[math\](.*)\[\/math\]/sU",
            function ($matches) {
                return "$" . $this->detex($matches[1]) . "$";
            }, $text);

        // bold
        $text = $this->preg_replace_multiple('/\[b\](.*)\[\/b\]/U', '{\bf{}$1}', $text);
        // underlined
        $text = $this->preg_replace_multiple('/\[u\](.*)\[\/u\]/U', '\underline{$1}', $text);
        // italic
        $text = $this->preg_replace_multiple('/\[i\](.*)\[\/i\]/U', '{\em{}$1}', $text);
        // subscript/superscript
        $text = preg_replace('/\[sub\](.*)\[\/sub\]/U', '\textsubscript{$1}', $text);
        $text = preg_replace('/\[sup\](.*)\[\/sup\]/U', '\textsuperscript{$1}', $text);
        // buttons
        $text = preg_replace('/\[button\\\_small\](.*)\[\/button\\\_small\]/U',
            '\setlength{\fboxsep}{2pt}\ovalbox{$1}\setlength{\fboxsep}{0pt}', $text);
        $text = preg_replace('/\[button\](.*)\[\/button\]/U',
            '\setlength{\fboxsep}{3pt}\ovalbox{$1}\setlength{\fboxsep}{0pt}', $text);
        // line break
        $text = str_replace("\n", "\n\n", $text);
        $text = str_replace("[br]", "\n\n", $text);
        $text = str_replace("<br>", "\n\n", $text);
        // code
        $text = preg_replace_callback("/\[code\](.*)\[\/code\]/sU",
            function ($matches) {
                // remove duplicate blank lines, https://stackoverflow.com/a/9058109/1044586
                return "\\texttt{" . preg_replace("/[\r\n]+/", "\n\\\\\\", $matches[1]) . "}";
                // return "\\texttt{" . str_replace("\n", "\n\\", $matches[1]) . "}";
            }, $text);
        // $text = preg_replace('/\[code\](.+)\[\/code\]/U', '\texttt{$1}', $text);

        // TODO: perhaps this should be done similarly as above for [code]
        $text = preg_replace_callback('/\[font=Courier New\](.*)\[\/font\]/sU',
            function ($matches) {
                $SEPARATOR = "§"; // maybe a different character would be better
                return ' \verb' . $SEPARATOR . $this->remove_whitespaces_bb($matches[1]) . $SEPARATOR . " ";
            }, $text);
        // color
        if ($this->TEXTCOLOR) {
            // \textcolor does not work along multiple paragraphs
            $text = preg_replace_callback(
                '/\[color=#(\w+)\]/U',
                function ($m) {
                    return '\color[HTML]{' . strtoupper($m[1]). "}{}";
                },
                $text
            );
            $text = preg_replace('/\[color=(\w+)\]/U',
                '\color{$1}{}', $text);
            $text = str_replace("[/color]", '\color{black}{}', $text);
        } else {
            $text = preg_replace('/\[color=(\w+)\]/U', '\it{}', $text); // use italic only when disabled
            $text = str_replace("[/color]", "\\color{black}{}", $text);
        }
        // url
        $text = preg_replace_callback('/\[url=(.+)\](.+)\[\/url\]/sU',
            function ($matches) {
                return "\\href{" . $this->detex($matches[1]) . "}{" . trim(str_replace("\n", " ", $matches[2])) . "}";
            },
            $text);
        $text = preg_replace_callback('/\[url=(.+)\]\[\/url\]/sU',
            function ($matches) {
                return "\\href{" . $this->detex($matches[1]) . "}{" . trim(str_replace("\n", " ", $matches[1])) . "}";
            },
            $text);
        // quote
        $text = preg_replace('/\[quote\](.*)\[\/quote\]/sU', "\\begin{quote}$1\\end{quote}", $text);
        // list
        //$text = preg_replace('/\[list\](.+)\[\/list\]/sU', "\\begin{itemize}\n$1\n\\end{itemize}\n", $text);
        //$text = preg_replace('/\[list=1\](.+)\[\/list\]/sU', "\\begin{enumerate}\n$1\n\\end{enumerate}\n", $text);
        $text = str_replace('[list]','\begin{enumerate}[label=$\bullet$]',$text);
        $text = str_replace('[list=1]','\begin{enumerate}[label=\arabic*.]',$text);
        $text = str_replace('[list=a]','\begin{enumerate}[label=(\alph*)]',$text);
        $text = str_replace('[/list]','\end{enumerate}',$text);
        $text = str_replace("[*]", "\\item ", $text);
        $text = str_replace("[/*]", "", $text);
        // ignore size, center, left, font
        // This will not work for nested [size] which is a typical error in BBcode.
        // Instead, http://stackoverflow.com/questions/12912165/php-nested-templates-in-preg-replace should be used,
        // but it would be too time consuming here to implement that right now.
        // $text = preg_replace('/\[size=(.+)\](.+)\[\/size\]/sU', "$2", $text);
        // So, instead we simply remove them.
        $text = preg_replace('/\[center\](.*)\[\/center\]/sU', "$1", $text);
        $text = preg_replace('/\[left\](.*)\[\/left\]/sU', "$1", $text);
        $text = preg_replace('/\[font=(.+)\](.*)\[\/font\]/sU', "$2", $text);
        // ignore remaining "size..." things (fixme):
        $text = preg_replace('/\[size=(.+)\]/sU', "", $text);
        $text = preg_replace('/\[\/size\]/', "", $text);
        // table (fixme: LLLL... is ugly---but always works!)
        $text = preg_replace('/\[table\](.*)\[\/table\]/sU', "{\\small" .
            "\\begin{tabulary}{{$this->MAX_COLUMN_WIDTH}\\textwidth}{LLLLLLLLLLLLLL}\n\\\\specialrule{0em}{0.3em}{0.3em}\n$1\\end{tabulary}\n}\n\n", $text);
        // "\\begin{tabularx}{{$this->MAX_COLUMN_WIDTH}\\textwidth}{XXXXXXXXXXX}\n$1\\end{tabularx}\n}\n\n", $text);
        $text = preg_replace('/\[table\]\[\/table\]/sU', "", $text);
        $text = preg_replace('/\[td\]\[\/td\]/sU', "&", $text);
        $text = preg_replace_callback('/\[td\](.*)\[\/td\]/sU',
            function ($matches) {
                // return "\\parbox[c]{\\hsize}{" . $this->textprocessor(trim($matches[1])). "}&";
                return "\\pbox[c]{{$this->MAX_COLUMN_WIDTH}\\textwidth}{" . $this->textprocessor(trim($this->cell_newlines($matches[1]))) . "}&";
                // return $this->textprocessor(trim($matches[1])). "&";
            },
            $text);
        $text = preg_replace('/\[tr\](.*)\[\/tr\]/sU', "$1\\\\\\\\\\\\specialrule{0em}{0.3em}{0.3em}\n", $text);

        // inline img
        $text = preg_replace_callback('/\[img\](.*)\[\/img\]/sU',
            function ($matches) {
                $img = $this->detex($matches[1]);
                $filename = $this->images_dir . "/" . sha1($img);
                if (!file_exists($filename) || !$this->IMAGE_CACHED) {
                    $this->verbose("Current dir is " . getcwd());
                    $this->verbose("Downloading image $img to $filename");
                    $contents = file_get_contents($img);
                    $this->verbose("Content length: " . strlen($contents));
                    file_put_contents($filename, $contents);
                    $command = "convert " . $filename . "[0] png:$filename.png";
                    $this->verbose("Converting image: $command");
                    passthru($command);
                }
                $img2 = imagecreatefrompng($filename);
                $width = imagesx($img2);
                $height = imagesy($img2);
                $this->verbose("Image width and height: " . $width . " and " . $height);
                // Correction (heuristic).
                $realheight = $height / $this->IMAGE_RATIO;
                if ($realheight > $this->MAXHEIGHT_INLINE_MM)
                    $realheight = $this->MAXHEIGHT_INLINE_MM;
                if ($realheight < $this->MINHEIGHT_INLINE_MM)
                    $realheight = $this->MINHEIGHT_INLINE_MM;
                // return "\\includegraphics[scale=$this->INLINE_IMG_SCALE]{{$filename}}";
                return "\\includegraphics[height=${realheight}mm]{{$filename}}";
            },
            $text);
        // inline icon
        $text = preg_replace_callback('/\[icon\](.*)\[\/icon\]/sU',
            function ($matches) {
                $img = $this->detex($matches[1]);
                $filename = $this->images_dir . "/" . sha1($img);
                if (!file_exists($filename) || !$this->IMAGE_CACHED) {
                    $this->verbose("Downloading image");
                    file_put_contents($filename, file_get_contents($img));
                    $command = "convert " . $filename . "[0] png:$filename.png";
                    passthru($command);
                }
                return "\\includegraphics[width={$this->INLINE_ICON_SIZE}cm]{{$filename}}";
            },
            $text);

        // turn 0xc2a0 into space
        $text = str_replace(chr(194) . chr(160), " ", $text);

        // turn 0xfeff into empty string (doesn't work)
        // $text = str_replace(chr(254) . chr(255), "", $text);

        // convert some special characters
        $text = str_replace("€", "\\euro{}", $text);
        // $text = str_replace("%", "\\%", $text);

        $text = preg_replace("/(α|β|Γ|γ|Δ|δ|ε|ζ|η|Θ|θ|Ι|ι|κ|Λ|λ|μ|ν|Ξ|ξ|Ο|ο|Π|π|ρ|Σ|σ|ς|τ|υ|Φ|φ|χ|Ψ|ψ|Ω|ω)/", '\textgreek{$1}', $text);

        // This list is incomplete. On an error and trial base it should be extended.
        // Also the lists at http://kinon.sakura.ne.jp/doc/LaTeX-Unicode.html and
        // https://en.wikipedia.org/wiki/Wikipedia:LaTeX_symbols may be helpful.
        $text = str_replace("→", '$\rightarrow$', $text);
        $text = str_replace("↦", '$\mapsto$', $text);
        $text = str_replace("−", '$-$', $text);
        $text = str_replace("–", '$-$', $text);
        $text = str_replace("⊥", '$\perp$', $text);
        $text = str_replace("°", '\degree', $text);
        $text = str_replace("▶", '$\blacktriangleright$', $text);
        $text = str_replace("∢", '$\angle$', $text);
        $text = str_replace("≤", '$\leq$', $text);
        $text = str_replace("◊", '$\lozenge$', $text);
        $text = str_replace("↶", '$\curvearrowleft$', $text);
        $text = str_replace("∙", '$\bullet$', $text);
        $text = str_replace("⊕", '$\oplus$', $text);
        $text = str_replace("⊖", '$\ominus$', $text);
        // The list should be maintained somewhat more programmatically. FIXME

        if ($this->WORDWRAP)
            $text = wordwrap($text);
        $this->verbose("textprocessor -> ..." . strlen($text) . "..." , 2);

        return $text;
    }

    private function startsWith($haystack, $needle)
    {
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }

    private function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }
        return (substr($haystack, -$length) === $needle);
    }

    private function translate($key) {
        if ($this->view) {
            return $this->view->translate($key);
        }
        return str_replace("_", "\\_", $key);
    }

    public function createLaTeXStream($inputfile)
    {

        $DOM = new DOMDocument;
        $DOM->loadHTML(file_get_contents($inputfile));

        $tex = "";
        $elems = "";
        // Creating LaTeX headers.
        $tex .= "% This LaTeX document has been machine generated by the GeoGebra Materials platform on ";
        $tex .= date("Y-m-d H:i:s") . "." . PHP_EOL;
        $tex .= "% Please visit https://www.geogebra.org for more information." . PHP_EOL . PHP_EOL;

        $tex .= "\\documentclass{article}" . PHP_EOL;
        $tex .= "\\usepackage{fullpage}" . PHP_EOL;
        $tex .= "\\usepackage[utf8]{inputenc}" . PHP_EOL;
        // for $\blacktriangleright$
        $tex .= "\\usepackage{amssymb}" . PHP_EOL;
        // for $\text{...}$ (inserted by the LaTeX plugin on the web site)
        $tex .= "\\usepackage{amsmath}" . PHP_EOL;

        $tex .= "\\usepackage{pbox}" . PHP_EOL;

        $tex .= "\\usepackage{fancybox}" . PHP_EOL;

        // GeoGebraBooks are sans-serif.
        $tex .= "\\renewcommand{\\familydefault}{\sfdefault}" . PHP_EOL;

        // Create a new command for URLs not needing references later
        $tex .= "\\newcommand{\hrefnoref}{\href}" . PHP_EOL;

        // Increase parskip for the abstract environment:
        $tex .= "\\renewenvironment{abstract}" . PHP_EOL;
        $tex .= "{\small \begin{center} \bfseries \abstractname\\vspace{-.5em}\\vspace{0pt}" . PHP_EOL;
        $tex .= "\\end{center} \list{}{%" . PHP_EOL;
        $tex .= "\setlength{\parskip}{4pt}}%" . PHP_EOL;
        $tex .= "\item\\relax}" . PHP_EOL;
        $tex .= "{\\endlist}" . PHP_EOL;

        // For bbcode colors
        $tex .= "\\usepackage{xcolor}" . PHP_EOL;

        // For images
        $tex .= "\\usepackage{graphicx}" . PHP_EOL;
        $tex .= "\\DeclareGraphicsExtensions{.pdf,.png,.jpg}" . PHP_EOL;

        // For references
        $tex .= "\\usepackage{hyperref}" . PHP_EOL;
        $tex .= "\\usepackage{breakurl}" . PHP_EOL;

        // For tables
        $tex .= "\\usepackage{tabulary}" . PHP_EOL;
        $tex .= "\\usepackage{ctable}" . PHP_EOL;
        // $tex .= "\\tymin=20pt". PHP_EOL; // unsure if useful

        // For quotes
        // $tex .= "\\usepackage[greek,main=" . $this->getLanguageBabel() . "]{babel}" . PHP_EOL;
        $tex .= "\\usepackage[greek," . $this->getLanguageBabel() . "]{babel}" . PHP_EOL;

        // For localized date
        $tex .= "\\usepackage{datetime}" . PHP_EOL;

        $tex .= "\\usepackage{csquotes}" . PHP_EOL;
        $tex .= "\\MakeOuterQuote{\"}" . PHP_EOL;
        // Note: csquotes will break the quotes heavily if there are structural bugs in the document FIXME
        // Fix wrong quotation marks in \texttt{...}, https://tex.stackexchange.com/a/311210/128521
        $tex .= "\\begingroup\\lccode`~=`\"" . PHP_EOL;
        $tex .= "\\lowercase{\\endgroup" . PHP_EOL;
        $tex .= "\\DeclareTextFontCommand{\\texttt}{\\ttfamily\def~{\"}}%" . PHP_EOL;
        $tex .= "}" . PHP_EOL;

        // For positioning images
        $tex .= "\\usepackage{float}" . PHP_EOL;

        // For unnumberred captions
        $tex .= "\\usepackage{caption}" . PHP_EOL;

        // For the euro symbol
        $tex .= "\\usepackage[official]{eurosym}" . PHP_EOL;
        // For the degree symbol
        $tex .= "\\usepackage{gensymb}" . PHP_EOL;

        // For subscript
        $tex .= "\\usepackage{fixltx2e}" . PHP_EOL;

        // For handling itemize and enumerate equivalently
        $tex .= "\\usepackage{enumitem}" . PHP_EOL;

        // Avoid long lines
        $tex .= "\\tolerance10000" . PHP_EOL;
        // Do not add extra space after period characters
        $tex .= "\\frenchspacing" . PHP_EOL;
        // Don't indent non-first lines
        if ($this->NOINDENT)
            $tex .= "\\setlength{\\parindent}{0pt}" . PHP_EOL;

        $tex .= PHP_EOL . "\\begin{document}" . PHP_EOL;
        // For better line spacing in tables
        // $tex .= "\\renewcommand{\\arraystretch}{2.5}" . PHP_EOL;

        $tex .= "\\setlength{\\parskip}{6pt plus0.2pt minus0.2pt}" . PHP_EOL;
        // No padding between fbox and graphics
        $tex .= "\\setlength{\\fboxsep}{0pt}" . PHP_EOL;

        $tex .= PHP_EOL . "\\selectlanguage{" . $this->getLanguage() . "}" . PHP_EOL;
        $title = $DOM->getElementsByTagName('title')->item(0)->nodeValue;
        $endtext = "- GeoGebraBook";
        if ($this->endsWith($title, $endtext)) {
            $title = substr($title, 0, strlen($title) - strlen($endtext) - 1); // . "\\footnote{GeoGebraBook}";
        }

        $tex .= "\\title{{$title}}" . PHP_EOL;

        $xpath = new DOMXPath($DOM);

        // setting author
        $authorname = $xpath->query('//*/p[@class="author"]/a');
        $author = trim($authorname->item(0)->nodeValue);
        $authorlink = $authorname->item(0)->getAttribute("href");
        $tex .= "\\author{\hrefnoref{{$authorlink}}{{$author}}}" . PHP_EOL;
        // setting date
        $dateinfo = $xpath->query('//*/p[@class="author"]');
        $datedata = trim($dateinfo->item(0)->nodeValue);
        // ..., Nov 17, 2016
        // This may be already internationalized, so we just copy it.
        $datefull = mb_split(",", $datedata)[1];
        // TODO: Ask the Materials Team to use the language of the document, not the user's
        // $datefull = substr($datedata,strlen($datedata)-12);
        // $time = strtotime($datefull);
        // $year = strftime("%Y", $time);
        // $month = strftime("%m", $time);
        // $day = strftime("%d", $time);
        // $tex .= "\\newdate{date}{{$day}}{{$month}}{{$year}}" . PHP_EOL;
        // $tex .= "\date{\displaydate{date}}" . PHP_EOL;
        $tex .= "\date{{$datefull}}" . PHP_EOL;

        $tex .= "\\maketitle" . PHP_EOL;

        $description = $xpath->query('//*/p[@class="description"] | //*/p[@class="bbcode-text description"]');
        $abstract = $this->textprocessor_start(trim($description->item(0)->nodeValue));
        if ($abstract != "")
            $tex .= PHP_EOL . "\\begin{abstract}" . PHP_EOL .
                $abstract . PHP_EOL .
                "\\end{abstract}" . PHP_EOL . PHP_EOL;

        $tex .= "\\tableofcontents" . PHP_EOL;

        // Creating images folder
        if (!file_exists($this->images_dir))
            mkdir($this->images_dir);

        // 1. Reading worksheets, each by each. Later we will insert them in each subsection.
        $worksheet = $xpath->query('//*/div[@class="worksheet_tbl"] | //*/table[@class="worksheet_tbl"]');
        $ws = 0;
        foreach ($worksheet as $w) {
            $this->verbose("Processing worksheet " . ($ws + 1));
            $elems[$ws] = "";
            $worksheet_elems = $xpath->query('div/div | tbody/tr/td', $w);
            foreach ($worksheet_elems as $we) {
                $type = $we->getAttribute("class");
                $style = $we->getAttribute("style");
                $this->verbose("type==" . $type, 2);
                if ($this->startsWith($type, "ws-element-text") || $type == "bbcode-text")
                    $elems[$ws] .= $this->textprocessor_start(trim($we->nodeValue)) . PHP_EOL . PHP_EOL;
                elseif ($type == "ws-element-title content-added-title") {
                    $text = $this->textprocessor_start(trim($we->nodeValue));
                    if ($text != "")
                        $elems[$ws] .= "\\subsubsection*{" . $text . "}" . PHP_EOL . PHP_EOL;
                } elseif ($type == "ws-element-applet" || $type == "ws-element-exercise" || $style == "padding: 0") {
                    $file = "";
                    $data = trim($we->nodeValue);
                    // ...applet_136381.setPreviewImage('GeoGebra/files/00/00/13/63/material-136381.png', 'GeoGebra/images/GeoGebra_loading.png');...
                    $this->verbose("preg_match(..." . strlen($data) . "...)", 2);
                    preg_match('/setPreviewImage\(\'(\S+)\'/s', $data, $matches);
                    $this->verbose(count($matches) . " matches", 2);
                    if (count($matches) > 0) {
                        $file = mb_substr($matches[0], strpos($matches[0], "'") + 1);
                        $file = rtrim($file, "'");
                    }
                    if ($file != "")
                        $this->verbose("figure($file)", 2);
                    $material = mb_substr($file, strpos($file, "-"));
                    $material = mb_substr($material, 1, strlen($material) - 5);

                    if ($material == "") {
                        // no preview image found
                        $img = "https://static.geogebra.org/images/geogebra-logo-name-1024.png";

                        preg_match('/_(\S+).setPreviewImage/s', $data, $matches);
                        $this->verbose(count($matches) . " matches", 2);
                        if (count($matches) > 0) {
                            $material_id = mb_substr($matches[0], strpos($matches[0], "'") + 1);
                            // TODO: we may want to search for this material in the database and find a proper link
                        }
                        $filename = $this->images_dir . "/placeholder.png";
                        if (!file_exists($filename) || !$this->IMAGE_CACHED) {
                            $this->verbose("Downloading placeholder");
                            file_put_contents($filename, file_get_contents($img));
                        }
                        // $elems[$ws] .= $this->figure($filename);
                        $elems[$ws] .= "\\begin{figure}[H]\n" . "\\begin{center}\n" .
                            "\\includegraphics[width=50mm]{images/placeholder}" .
                            "\\end{center}\n" . "\\end{figure}\n\n";
                        // TODO: We may want to use the dimensions from the JavaScript code
                    } else {
                        $link = "http://www.geogebra.org/m/" . $material;
                        $caption = $this->translate("element_applet_title") . ", \\url{{$link}}";
                        $elems[$ws] .= $this->figure($file, $caption, true, $link);
                    }
                } elseif ($type == "ws-element-image") {
                    $img = $xpath->query('img', $we);
                    $file = $img->item(0)->getAttribute("src");
                    $caption_div = $xpath->query('div[@class="image-description bbcode-text"]', $we);
                    $caption = $caption_div->item(0)->nodeValue;
                    if ($caption == "undefined") {
                        $caption = NULL;
                    } else {
                        $caption=$this->textprocessor_start(trim($caption));
                    }
                    $elems[$ws] .= $this->figure($file, $caption);
                } elseif ($type == "ws-element-video") {
                    $img = $xpath->query('div/iframe', $we);
                    $src = $img->item(0)->getAttribute("src");
                    $urlpos = strpos($src, "/embed/");
                    // ... src="http://www.youtube.com/embed/V-Cq2VMsiZw" ...
                    if ($urlpos != FALSE) {
                        // http://img.youtube.com/vi/V-Cq2VMsiZw/maxresdefault.jpg
                        $videoid = mb_substr($src, $urlpos + 7); // "/embed/"
                        $preview = "http://img.youtube.com/vi/" . $videoid .
                            "/maxresdefault.jpg";
                        $filename = $this->images_dir . "/" . sha1($preview);
                        if (!file_exists($filename) || !$this->IMAGE_CACHED) {
                            $this->verbose("Downloding YouTube thumbnail for video $preview");
                            file_put_contents($filename, file_get_contents($preview));
                        }
                        $caption = $this->translate("element_video_title") . ", \\url{https://www.youtube.com/watch?v={$videoid}}";
                        $elems[$ws] .= $this->figure($filename, $caption);
                    }
                } elseif ($this->startsWith($type, "ws-element-header")) {
                    $text = $this->textprocessor_start(trim($we->nodeValue));
                    if ($text != "")
                        $elems[$ws] .= "\\subsubsection*{" . $text . "}" . PHP_EOL . PHP_EOL;  // paragraph looks problematic sometimes, FIXME
                } // Unsupported media
                else {
                    // $elems[$ws] .= $this->infobox("Unsupported media type $type");
                }
            }
            $this->verbose(count($elems[$ws]) . " elements found for worksheet " . ($ws + 1));
            $ws++;
        }

        // 2. Reading the menu to get document structure.
        $this->verbose("Generating sections");
        $entries = $xpath->query('//html/body/div[@id="content"]/div[@id="menu-container"]/div[@id="menu"]/div[@id="sBook-menulist"]/ul/li[@class="menu-opened"]/div[@class="menu-wrapper"]');
        $this->verbose(print_r($entries, TRUE), 2);

        $ws = 0;
        for ($i = 0; $i < $entries->length; $i++) {
            $this->verbose("Section " . ($i + 1), 2);
            $entry = $entries->item($i);
            $section_lookup = $xpath->query('div[@class="menu-item"]/p', $entry);
            $section = trim($section_lookup->item(0)->nodeValue);
            $this->verbose("Section '$section'", 2);
            $space = mb_strpos($section, " ");
            // In simple worksheets the section names are not numbered and there would be only 1 section
            // if we follow our main logic. But we don't need a big section with small subsections,
            // but sections instead. The first three characters are normally "1. "
            // in the section name, but for simple worksheets this leading part is omitted.
            // So we simply check here if we are in section 1 and it has numeration or not.
            $simple = false;
            if ($i == 0 && !($this->startsWith($section, "1. ")))
                $simple = true;
            else
                $section = substr($section, $space + 1);

            $subsection_lookup = $xpath->query('div[@class="submenu-items"]/ol/li', $entry);
            $this->verbose(print_r($subsection_lookup, TRUE), 2);
            if ($subsection_lookup->length > 0 && !$simple) // don't create this for simple worksheets
                $tex .= "\\section{{" . $this->entex($section) . "}}" . PHP_EOL;

            foreach ($subsection_lookup as $subentry) {
                $subsection = $subentry->nodeValue;
                $tex .= "\\";
                if (!$simple)
                    $tex .= "sub";
                $tex .= "section{{" . $this->entex($subsection) . "}}" . PHP_EOL;
                $tex .= $elems[$ws];
                $ws++;
            }
        }

        // 3. Creating references.
        if ($this->REFERENCES) {
            $this->verbose("Creating references");
            preg_match_all("/\\\\href{(.*)}{(.+)}/sU", $tex, $matches, PREG_PATTERN_ORDER);
            // Re-linking non-first occurences of the same link, and re-numbering as well.
            $count = count($matches[2]);
            $relink = array();
            $renum = array();
            for ($i = 0, $currnum = 0; $i < $count; $i++) {
                if (!array_key_exists($i, $relink)) {
                    $this->verbose("Checking repetitions of reference $i ($currnum)...");
                    $relink[$i] = $i;
                    for ($j = $i + 1; $j < $count; $j++) {
                        if ($matches[2][$i] == $matches[2][$j]) {
                            $this->verbose("...found $j");
                            $relink[$j] = $i;
                        }
                    }
                    $renum[$i] = $currnum;
                    $currnum++;
                }
            }
            $count = count($matches[1]);
            // Appending \href's by [$ref]
            for ($i = 0; $i < $count; $i++) {
                $ref = $renum[$relink[$i]] + 1;
                $this->verbose("Changing " . $matches[0][$i] . " to " . $matches[0][$i]);
                // Ensure that this change should not be done more than once, so
                // by using this dirty trick we scramble $matches[0][$1] a bit:
                $scrambled_matches0i = str_replace("href", "href ", $matches[0][$i]);
                $tex = str_replace($matches[0][$i], $scrambled_matches0i . " \\textnormal{\\cite{{" . $ref . "}}}", $tex);
            }
            if ($count > 0) {
                // Adding References
                $tex .= "\\begin{thebibliography}{{$count}}" . PHP_EOL;
                for ($i = 0; $i < $count; $i++) {
                    // Only non-relinked references should be put in the bibliography
                    if ($relink[$i] == $i) {
                        $ref = $renum[$i] + 1;
                        $tex .= "\\bibitem[{$ref}]{{$ref}}{\url{{".$this->myUrlEncode($matches[1][$i])."}}}" . PHP_EOL;
                    }
                }
                $tex .= "\\end{thebibliography}" . PHP_EOL;
            }
        }

        // LaTeX footer.
        $tex .= PHP_EOL . "\\end{document}" . PHP_EOL;

        // Final beautifying.
        $tex = preg_replace("/\n\n\n+/", "\n\n", $tex);

        return $tex;
    }

    private function figure($file, $caption = NULL, $fbox = FALSE, $link = NULL)
    {
        $this->imgno++;
        $this->verbose("imgno $this->imgno");

        // if (!file_exists($this->images_dir))
        //    mkdir($this->images_dir);

        if (!file_exists("$this->images_dir/$this->imgno.png") || !$this->IMAGE_CACHED) {
            $this->verbose("Converting $file to Figure $this->imgno as $this->images_dir/$this->imgno.png...");
            // FIXME: we should use the information taken from the HTML instead of this hack (sizes for GeoGebraTube)
            `convert "$file"[0] -trim png:$this->images_dir/$this->imgno.png`;
        }

        $img = imagecreatefrompng("$this->images_dir/$this->imgno.png");
        $width = imagesx($img);
        $height = imagesy($img);

        // Correction (heuristic).
        $realwidth = $width / $this->IMAGE_RATIO;
        if ($realwidth > $this->MAXWIDTH_MM)
            $realwidth = $this->MAXWIDTH_MM;
        if ($realwidth < $this->MINWIDTH_MM)
            $realwidth = $this->MINWIDTH_MM;

        $text = "\\begin{figure}[H]" . PHP_EOL .
            "\\begin{center}" . PHP_EOL;
        if ($link != NULL) {
            $text .= "\hrefnoref{" . $link . "}{";
        }
        if ($fbox)
            $text .= "\\fbox{";
        $text .= "\\includegraphics[width={$realwidth}mm]{{$this->images_dir}/{$this->imgno}}";
        if ($fbox)
            $text .= "}";
        if ($link != NULL) {
            $text .= "}";
        }
        $text .= PHP_EOL;
        if ($caption != NULL)
            $text .= "\\caption*{{$caption}}" . PHP_EOL;
        $text .= "\\end{center}" . PHP_EOL .
            "\\end{figure}" . PHP_EOL . PHP_EOL;
        return $text;
    }

    function verbose($text, $level = NULL)
    {
        if ($this->verbosity) {
            if ($level == NULL || ($level != NULL && $level <= $this->verbosity))
                echo date(DATE_RFC2822), " ", $text, PHP_EOL;
            // TODO: Use a more sophisticated way to save the logs to a file.
            $f = fopen("/tmp/pdf.log", "a");
            fprintf($f, date(DATE_RFC2822) . " " . $text . PHP_EOL);
            fclose($f);
        }
    }

    function infobox($text)
    {
        return PHP_EOL . PHP_EOL . "\\begin{center}" . PHP_EOL .
            "\\framebox{{$text}}" . PHP_EOL .
            "\\end{center}" . PHP_EOL . PHP_EOL;
    }

    function iso2babel($in, $babel = true)
    {
        $out = "english";
        if ($in == "hu_HU" || $in == "hu")
            $out = "magyar";
        if ($in == "de")
            $out = "german";
        if ($in == "de_AT")
            $out = "austrian";
        if (!$babel && ($in == "de" || $in == "de_AT"))
            $out = "n" . $out; // German quotation marks are difficult...
        return $out;
    }

}

?>

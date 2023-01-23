<?php

namespace acdhOeaw\arche\fileChecker;

use acdhOeaw\arche\fileChecker\Misc as MC;

class HtmlOutput {

    /**
     * 
     * Generate HTML file from the error list
     * 
     */
    public function generateErrorListHtml(string $directory): void {
        $errorList = <<<HTML
        <div class="card" id="errors">
            <div class="header">
                <h4 class="title">Errors</h4>                            
            </div>
            <div class="content table-responsive table-full-width">
                <table class="table table-hover table-striped" id="errorsDT">
                    <thead>
                        <tr><th><b>ErrorType</b></th><th><b>Directory</b></th><th><b>File Name</b></th></tr>
                    </thead>
                </table>
            </div>
        </div>
        HTML;
        $this->writeDataToHtmlFile($errorList, $directory, "errorList");
    }

    /**
     * 
     * Generate HTML file from the file list
     * 
     */
    public function generateFileListHtml(string $directory): void {
        $fileList = <<<HTML
        <div class="card" id="filelist">
            <div class="header">
                <h4 class="title">File List</h4>
            </div>
            <div class="content table-responsive table-full-width" >';
                <table class="table table-hover table-striped" id="filesDT">
                    <h2>Files</h2>
                    <thead>
                        <tr>
                            <th><b>Filename</b></th>
                            <th><b>Directory</b></th>
                            <th><b>Extension</b></th>
                            <th><b>Type</b></th>
                            <th><b>Size</b></th>
                            <th><b>Last Modif.</b></th>
                            <th><b>Valid</b></th>
                        </tr>
                    </thead>\n";
                </table>
            </div>
        </div>
        HTML;
        $this->writeDataToHtmlFile($fileList, $directory, "fileList");
    }

    public function generateDirListHtml(string $directory): void {
        $fileList = <<<HTML
        <div class="card" id="filelist">
            <div class="header">
                <h4 class="title">Directory List</h4>
            </div>
            <div class="content table-responsive table-full-width">
                <table class="table table-hover table-striped" id="dirsDT">
                    <h2>Directories</h2>
                    <thead>
                        <tr>
                            <th><b>Directory</b></th>
                            <th><b>Valid</b></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
        HTML;
        $this->writeDataToHtmlFile($fileList, $directory, "directoryList");
    }

    /**
     * Creates the FileTypeList HTML
     * 
     */
    public function generateFileTypeJstreeHtml(string $directory): void {
        $fileList = <<<HTML
        <div class="card" id="fileTypeList">
            <div class="header">
                <h4 class="title">File Types By Directory</h4>
            </div>
            <div class="content table-responsive table-full-width">
                <div class="container-fluid">';
                    <input type="text" value="" style="box-shadow:inset 0 0 4px #eee; width:200px; margin:0; padding:6px 12px; border-radius:4px; border:1px solid silver; font-size:1.1em;" id="directories_q" placeholder="Search"/>
                    <div id="data" class="demo"></div>
                </div>
            </div>
        </div>
        <div class="card" id="fileTypeList">
            <div class="header">
                <h4 class="title">File Types By Extensions</h4>
            </div>
            <div class="content table-responsive table-full-width">
                <div class="container-fluid">';
                    <input type="text" value="" style="box-shadow:inset 0 0 4px #eee; width:200px; margin:0; padding:6px 12px; border-radius:4px; border:1px solid silver; font-size:1.1em;" id="extensions_q" placeholder="Search"/>
                    <div id="ext_data" class="demo"></div>
                </div>
            </div>
        </div>
        HTML;
        $this->writeDataToHtmlFile($fileList, $directory, "fileTypeList");
    }

    /**
     * 
     * Write string into HTML file
     * 
     * @param string $string
     * @param string $directory
     */
    public function writeDataToHtmlFile(string $string, string $directory,
                                        string $filename): void {

        if (empty($string) || empty($directory) || empty($filename)) {
            MC::die("writeDataToHtmlFile -> missing data");
        }
        $tmplDir = realpath(__DIR__ . '/../../../../template');
        copy($tmplDir . '/css/style.css', $directory . '/css/style.css');
        copy($tmplDir . '/js/jquery.js', $directory . '/js/jquery.js');
        copy($tmplDir . '/js/jstree.min.js', $directory . '/js/jstree.min.js');
        copy($tmplDir . '/css/jquery.dataTables.css', $directory . '/css/jquery.dataTables.css');
        copy($tmplDir . '/js/jquery.dataTables.js', $directory . '/js/jquery.dataTables.js');
        copy($tmplDir . '/js/helper.js', $directory . '/js/helper.js');
        copy($tmplDir . '/css/jstreecss.css', $directory . '/css/jstreecss.css');
        copy($tmplDir . '/css/throbber.gif', $directory . '/css/throbber.gif');
        copy($tmplDir . '/css/40px.png', $directory . '/css/40px.png');
        copy($tmplDir . '/css/32px.png', $directory . '/css/32px.png');
        copy($tmplDir . '/.htaccess', $directory . '/.htaccess');

        $template = file_get_contents($tmplDir . '/template.html');
        $tpl      = str_replace("{html_file_content}", $string, $template);
        file_put_contents($directory . '/' . $filename . '.html', $tpl . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public function generateFileTypeListHtml(string $directory): void {
        $obj = json_decode(file_get_contents("$directory/extensions.json"), true);
        if ($obj === false) {
            return;
        }

        $extensionList = [];
        foreach ($obj as $o) {
            $ext = $o['text'] ?? '';
            foreach ($o['children'] as $d) {
                $text                          = explode(':', $d['text']);
                $extensionList[$ext][$text[0]] = $text[1];
            }
        }
        ksort($extensionList, SORT_STRING);

        $fileList = '<div class="card" id="">
                        <div class="header">
                            <h4 class="title">File Types By Extensions</h4>
                        </div>
                    <div class="content table-responsive table-full-width" >';
        $fileList .= "<table class=\"table table-hover table-striped\" id=\"fileTypeDT\">\n";
        $fileList .= "<thead>\n";
        $fileList .= "<tr><th><b>Extension</b></th><th><b>Count</b></th><th><b>SumSize</b></th><th><b>MinSize</b></th><th><b>MaxSize</b></th></tr>\n";
        $fileList .= "</thead>\n";
        $fileList .= "<tbody>\n";
        foreach ($extensionList as $k => $v) {
            $fileList .= "<tr>\n";
            $fileList .= "<td width='10%'>{$k}</td>\n";
            $fileList .= "<td width='18%'>{$v['fileCount']}</td>\n";
            $fileList .= "<td width='18%'>{$v['SumSize']}</td>\n";
            $fileList .= "<td width='18%'>{$v['MinSize']}</td>\n";
            $fileList .= "<td width='18%'>{$v['MaxSize']}</td>\n";
            $fileList .= "</tr>\n";
        }
        $fileList .= "</tbody>\n";
        $fileList .= "</table>\n\n";

        $fileList .= "</div>\n\n";
        $fileList .= "</div>\n\n";
        $this->writeDataToHtmlFile($fileList, $directory, "fileTypeList");
    }
}

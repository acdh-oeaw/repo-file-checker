## phpMussel 中文（简体）文档。

### 内容
- 1. [前言](#SECTION1)
- 2. [如何安装](#SECTION2)
- 3. [如何使用](#SECTION3)
- 4. [前端管理](#SECTION4)
- 5. [CLI（命令行界面）](#SECTION5)
- 6. [文件在包](#SECTION6)
- 7. [配置选项](#SECTION7)
- 8. [签名格式](#SECTION8)
- 9. [已知的兼容问题](#SECTION9)
- 10. [常见问题（FAQ）](#SECTION10)

*翻译注释：如果错误（例如，翻译差异，错别字，等等），英文版这个文件是考虑了原版和权威版。如果您发现任何错误，您的协助纠正他们将受到欢迎。*

---


### 1. <a name="SECTION1"></a>前言

感谢使用phpMussel，这是一个根据ClamAV的签名和其他签名在上传完成后来自动检测木马/病毒/恶意软件和其他可能威胁到您系统安全的文件的PHP脚本。

PHPMUSSEL COPYRIGHT 2013 and beyond GNU/GPLv2 by Caleb M (Maikuolan)。

本脚本是基于GNU通用许可V2.0版许可协议发布的，您可以在许可协议的允许范围内自行修改和发布，但请遵守GNU通用许可协议。使用脚本的过程中，作者不提供任何担保和任何隐含担保。更多的细节请参见GNU通用公共许可证，下的`LICENSE.txt`文件也可从访问：
- <http://www.gnu.org/licenses/>。
- <http://opensource.org/licenses/>。

谢谢[ClamAV](http://www.clamav.net/)为本脚本提供文件签名库访问许可。没有它，这个脚本很可能不会存在，或者其价值有限。

谢谢Sourceforge和GitHub开通了，[Spambot Security](http://www.spambotsecurity.com/forum/viewforum.php?f=55)的phpMussel的讨论论坛，谢谢为phpMussel提供签名文件的：[SecuriteInfo.com](http://www.securiteinfo.com/)，[PhishTank](http://www.phishtank.com/)，[NLNetLabs](http://nlnetlabs.nl/)，还有更多的我忘了提及的人（抱歉，语文水平有限，这句话实在不知道怎么翻译才通顺）。

现在phpMussel的代码文件和关联包可以从以下地址免费下载：
- [Sourceforge](http://phpmussel.sourceforge.net/)。
- [GitHub](https://github.com/Maikuolan/phpMussel/)。

---


### 2. <a name="SECTION2"></a>如何安装

#### 2.0 安装手工（WEB服务器）

1） 在阅读到这里之前，我假设您已经下载脚本的一个副本，已解压缩其内容并保存在您的机器的某个地方。现在，您要决定将脚本放在您服务器上的哪些文件夹中，例如`/public_html/phpmussel/`或其他任何您觉得满意和安全的地方。*上传完成后，继续阅读。。*

2） 重命名`config.ini.RenameMe`到`config.ini`（位于内`vault`），和如果您想（强烈推荐高级用户，但不推荐业余用户或者新手使用这个方法），打开它（这个文件包含所有phpMussel的可用配置选项；以上的每一个配置选项应有一个简介来说明它是做什么的和它的具有的功能）。按照您认为合适的参数来调整这些选项，然后保存文件，关闭。

3） 上传（phpMussel和它的文件）到您选定的文件夹（不需要包括`*.txt`/`*.md`文件，但大多数情况下，您应上传所有的文件）。

4） 修改的`vault`文件夹权限为“755”（如果有问题，您可以试试“777”，但是这是不太安全）。注意，主文件夹也应该是该权限，如果遇上其他权限问题，请修改对应文件夹和文件的权限。

5） 接下来，您需要为您的系统或CMS设定启动phpMussel的钩子。有几种不同的方式为您的系统或CMS设定钩子，最简单的是在您的系统或CMS的核心文件的开头中使用`require`或`include`命令直接包含脚本（这个方法通常会导致在有人访问时每次都加载）。平时，这些都是存储的在文件夹中，例如`/includes`，`/assets`或`/functions`等文件夹，和将经常被命名的某物例如`init.php`，`common_functions.php`，`functions.php`。这是根据您自己的情况决定的，并不需要完全遵守；如果您遇到困难，参观GitHub上的phpMussel问题页面和/或访问phpMussel支持论坛和发送问题；可能其他用户或者我自己也有这个问题并且解决了（您需要让我们您在使用哪些CMS）。为了使用`require`或`include`，插入下面的代码行到最开始的该核心文件，更换里面的数据引号以确切的地址的`loader.php`文件（本地地址，不是HTTP地址；它会类似于前面提到的vault地址）。

`<?php require '/user_name/public_html/phpmussel/loader.php'; ?>`

保存文件，关闭，重新上传。

-- 或替换 --

如果您使用Apache网络服务器并且您可以访问`php.ini`，您可以使用该`auto_prepend_file`指令为任何PHP请求创建附上的phpMussel。就像是：

`auto_prepend_file = "/user_name/public_html/phpmussel/loader.php"`

或在该`.htaccess`文件：

`php_value auto_prepend_file "/user_name/public_html/phpmussel/loader.php"`

6） 到这里，您已经完成安装，现在您应测试phpMussel以确保它的正常运行！为了保护系统中的文件（或者应该翻译为保护上传的文件），可以尝试通过常用的浏览器上传的方式上传包含在`_testfiles`文件夹内的内容到您的网站。如果一切正常，phpMussel应该出现阻止上传信息，如果出现什么不正常情况例如您使用了其他高级的功能或使用的其它类型的扫描，我建议尝试它跟他们一起使用以确保都能工作正常。

#### 2.1 安装手工（CLI）

1） 在阅读到这里之前，我假设您已经下载脚本并且已经解压缩并且保存在您指定的位置。

2） phpMussel需要PHP运行环境支持。如果您没有安装PHP，请安装。

3） 自定义（强烈推荐高级用户使用，但不推荐新手或没有经验的用户使用）：打开`config.ini`（位于内`vault`） - 这个文件包含phpMussel所有的配置选项。每选项应有一个简评以说明它做什么和它的功能。按照您认为合适的参数调整这些选项，然后保存文件，关闭。

4） 您如果您创建一个批处理文件来自动加载的PHP和phpMussel，那么使用phpMussel的CLI模式将更加方便。要做到这一点，打开一个纯文本编辑器例如Notepad或Notepad++，输入php.exe的完整路径（注意是绝对路径不是相对路径），其次是一个空格，然后是`loader.php`的路径（同php.exe），最后，保存此文件使用一个`.bat`扩展名放在常用的位置；在您指定的位置，能通过双击您保存的`.bat`文件来调用phpMussel。

5） 到这里，您完成了CLI模式的安装！当然您应测试以确保正常运行。如果要测试phpMussel，请通过phpMussel尝试扫描`_testfiles`文件夹内提供的文件。

#### 2.2 与COMPOSER安装

[phpMussel是在Packagist上](https://packagist.org/packages/maikuolan/phpmussel)，所以，如果您熟悉Composer，您可以使用Composer安装phpMussel（您仍然需要准备配置和钩子；参考“安装手工（WEB服务器）”步骤2和5）。

`composer require maikuolan/phpmussel`

---


### 3. <a name="SECTION3"></a>如何使用

#### 3.0 如何使用（对于WEB服务器）

phpMussel应该能够正确操作与最低要求从您：安装后，它应该立即开展工作和应该立即有用。

文件上传扫描是自动的和按照设定规则激活的，所以，您不需要做任何额外的事情。

另外，您能手动使用phpMussel扫描文件，文件夹或存档当您需要时。要做到这一点，首先，您需要确保`config.ini`文件（`cleanup`｢清理｣必须关闭）的配置是正常的，然后通过任何一个PHP文件的钩子至phpMussel，在您的代码中添加以下代码：

`$phpMussel['Scan']($what_to_scan, $output_type, $output_flatness);`

- `$what_to_scan`可以是字符串，数组，或多维数组，和表明什么文件，收集的文件，文件夹和／或文件夹至扫描。
- `$output_type`是布尔，和表明什么格式到回报扫描结果作为。False｢假/负｣指示关于功能以回报扫描结果作为整数（结果回报的-3表明问题是遇到关于phpMussel签名文件或签名MAP｢地图｣文件和表明他们可能是失踪或损坏，-2表明损坏数据是检测中扫描和因此扫描失败完成，-1表明扩展或插件需要通过PHP以经营扫描是失踪和因此扫描失败完成，0表明扫描目标不存在和因此没有任何事为扫描，1表明扫描目标是成功扫描和没有任何问题检测，和2表明扫描目标是成功扫描和至少一些问题是检测）。True｢真/正｣指示关于功能以回报扫描结果作为人类可读文本。此外，在任一情况下，结果可以访问通过全局变量后扫描是完成。变量是自选，确定作为False｢假/负｣作为标准。
- `$output_flatness`是布尔，表明如果回报扫描结果（如果有多扫描目标）作为数组或字符串。False｢假/负｣指示回报结果作为数组。True｢真/正｣负｣指示回报结果作为字符串。变量是自选，确定作为False｢假/负｣作为标准。

例子：

```PHP
 $results = $phpMussel['Scan']('/user_name/public_html/my_file.html', true, true);
 echo $results;
```

返回结果类似于（作为字符串）：

```
 Wed, 16 Sep 2013 02:49:46 +0000 Started.
 > Checking '/user_name/public_html/my_file.html':
 -> No problems found.
 Wed, 16 Sep 2013 02:49:47 +0000 Finished.
```

对一个签名类型进行完整的检查测试以及phpMussel如何扫描和使用签名文件，请参阅｢签名格式｣部分的自述文件。

如果您遇到任何误报，如果您遇到无法检测的新类型，或者关于签名的其他任何问题，请联系我以便于后续的版本支持，该，如果您不联系我，我可能不会知道并在下一版本中进行处理。

如果您遇到误报严重或者不需要检测该签名下的文件或者其他不需要使用签名验证的场景，请关闭签名验证，具体请参考｢前端管理｣部分的这个自述文件中的灰名单部分。

#### 3.1 如何使用（CLI）

请参考“安装手工（CLI）”部分的这个自述文件。

请注意，虽说未来版本的phpMussel应该支持其他系统，在这个时候，phpMussel CLI模式仅支持基于Windows系统（您可以，当然，尝试它在其他系统，但我不能保证它正常工作）。

还注意，phpMussel是“*一经请求*”扫描器；不是“*一经访问*”扫描器（除了文件上传，在上传时候），而不像传统的防病毒套件，它不监控活动内存！它将会只检测病毒从文件上传，而从那些具体文件您明确地告诉它需要扫描。

---


### 4. <a name="SECTION4"></a>前端管理

#### 4.0 什么是前端。

前端提供了一种方便，轻松的方式来维护，管理和更新phpMussel安装。 您可以通过日志页面查看，共享和下载日志文件，您可以通过配置页面修改配置，您可以通过更新页面安装和卸载组件，和您可以通过文件管理器上传，下载和修改文件在vault。

默认情况是禁用前端，以防止未授权访问 （未授权访问可能会对您的网站及其安全性造成严重后果）。 启用它的说明包括在本段下面。

#### 4.1 如何启用前端。

1) 里面的`config.ini`文件，找到指令`disable_frontend`，并将其设置为`false` （默认值为`true`）。

2) 从浏览器访问`loader.php` （例如，`http://localhost/phpmussel/loader.php`）。

3) 使用默认用户名和密码（admin/password）登录。

注意： 第一次登录后，以防止未经授权的访问前端，您应该立即更改您的用户名和密码！ 这是非常重要的，因为它可以任意PHP代码上传到您的网站通过前端。

#### 4.2 如何使用前端。

每个前端页面上都有说明，用于解释正确的用法和它的预期目的。 如果您需要进一步的解释或帮助，请联系支持。 另外，YouTube上还有一些演示视频。

---


### 5. <a name="SECTION5"></a>CLI（命令行界面）

在Windows系统上phpMussel在CLI模式可以作为一个互动文件执行扫描。参考｢如何安装（对于CLI）｣部分的这个自述文件为更信息。

为一个列表的可用CLI命令，在CLI提示，键入【c】，和按Enter键。

另外，对于那些有兴趣，一个视频教程如何使用phpMussel在命令行模式是可在这里：
- <https://www.youtube.com/watch?v=H-Pa740-utc>

---


### 6. <a name="SECTION6"></a>文件在包
（本段文件采用的自动翻译，因为都是一些文件描述，参考意义不是很大，如有疑问，请参考英文原版）

下面是一个列表的所有的文件该应该是存在在您的存档在下载时间，任何文件该可能创建因之的您的使用这个脚本，包括一个简短说明的他们的目的。

文件 | 说明
----|----
/_docs/ | 笔记文件夹（包含若干文件）。
/_docs/readme.ar.md | 阿拉伯文自述文件。
/_docs/readme.de.md | 德文自述文件。
/_docs/readme.en.md | 英文自述文件。
/_docs/readme.es.md | 西班牙文自述文件。
/_docs/readme.fr.md | 法文自述文件。
/_docs/readme.id.md | 印度尼西亚文自述文件。
/_docs/readme.it.md | 意大利文自述文件。
/_docs/readme.ja.md | 日文自述文件。
/_docs/readme.ko.md | 韩文自述文件。
/_docs/readme.nl.md | 荷兰文自述文件。
/_docs/readme.pt.md | 葡萄牙文自述文件。
/_docs/readme.ru.md | 俄文自述文件。
/_docs/readme.ur.md | 乌尔都文自述文件。
/_docs/readme.vi.md | 越南文自述文件。
/_docs/readme.zh-TW.md | 中文（传统）自述文件。
/_docs/readme.zh.md | 中文（简体）自述文件。
/_testfiles/ | 测试文件文件夹（包含若干文件）。所有包含文件是测试文件为测试如果phpMussel是正确地安装上您的系统，和您不需要上传这个文件夹或任何其文件除为上传测试。
/_testfiles/ascii_standard_testfile.txt | 测试文件以测试phpMussel标准化ASCII签名。
/_testfiles/coex_testfile.rtf | 测试文件以测试phpMussel复杂扩展签名。
/_testfiles/exe_standard_testfile.exe | 测试文件以测试phpMussel移植可执行｢PE｣签名。
/_testfiles/general_standard_testfile.txt | 测试文件以测试phpMussel通用签名。
/_testfiles/graphics_standard_testfile.gif | 测试文件以测试phpMussel图像签名。
/_testfiles/html_standard_testfile.html | 测试文件以测试phpMussel标准化HTML签名。
/_testfiles/md5_testfile.txt | 测试文件以测试phpMussel MD5签名。
/_testfiles/ole_testfile.ole | 测试文件以测试phpMussel OLE签名。
/_testfiles/pdf_standard_testfile.pdf | 测试文件以测试phpMussel PDF签名。
/_testfiles/pe_sectional_testfile.exe | 测试文件以测试phpMussel移植可执行｢PE｣部分签名。
/_testfiles/swf_standard_testfile.swf | 测试文件以测试phpMussel SWF签名。
/vault/ | 安全/保险库｢Vault｣文件夹（包含若干文件）。
/vault/cache/ | 缓存｢Cache｣文件夹（为临时数据）。
/vault/cache/.htaccess | 超文本访问文件（在这种情况，以保护敏感文件属于脚本从被访问由非授权来源）。
/vault/fe_assets/ | 前端资产。
/vault/fe_assets/.htaccess | 超文本访问文件（在这种情况，以保护敏感文件属于脚本从被访问由非授权来源）。
/vault/fe_assets/_accounts.html | 前端账户页面的HTML模板。
/vault/fe_assets/_accounts_row.html | 前端账户页面的HTML模板。
/vault/fe_assets/_config.html | 前端配置页面的HTML模板。
/vault/fe_assets/_config_row.html | 前端配置页面的HTML模板。
/vault/fe_assets/_files.html | 文件管理器的HTML模板。
/vault/fe_assets/_files_edit.html | 文件管理器的HTML模板。
/vault/fe_assets/_files_rename.html | 文件管理器的HTML模板。
/vault/fe_assets/_files_row.html | 文件管理器的HTML模板。
/vault/fe_assets/_home.html | 端主页的HTML模板。
/vault/fe_assets/_login.html | 前端登录的HTML模板。
/vault/fe_assets/_logs.html | 前端日志页面的HTML模板。
/vault/fe_assets/_nav_complete_access.html | 前端导航链接的HTML模板，由那些与完全访问使用。
/vault/fe_assets/_nav_logs_access_only.html | 前端导航链接的HTML模板，由那些与仅日志访问使用。
/vault/fe_assets/_updates.html | 前端更新页面的HTML模板。
/vault/fe_assets/_updates_row.html | 前端更新页面的HTML模板。
/vault/fe_assets/_upload_test.html | 上传测试页面的HTML模板。
/vault/fe_assets/frontend.css | 前端CSS样式表。
/vault/fe_assets/frontend.dat | 前端数据库（包含账户信息，会话信息，和缓存；只生成如果前端是启用和使用）。
/vault/fe_assets/frontend.html | 前端的主HTML模板文件。
/vault/fe_assets/icons.php | 图标处理文件（由前端文件管理器使用）。
/vault/fe_assets/pips.php | 点数处理文件（由前端文件管理器使用）。
/vault/lang/ | 包含phpMussel语言数据。
/vault/lang/.htaccess | 超文本访问文件（在这种情况，以保护敏感文件属于脚本从被访问由非授权来源）。
/vault/lang/lang.ar.fe.php | 阿拉伯文前端语言数据。
/vault/lang/lang.ar.php | 阿拉伯文语言数据。
/vault/lang/lang.de.fe.php | 德文前端语言数据。
/vault/lang/lang.de.php | 德文语言数据。
/vault/lang/lang.en.fe.php | 英文前端语言数据。
/vault/lang/lang.en.php | 英文语言数据。
/vault/lang/lang.es.fe.php | 西班牙文前端语言数据。
/vault/lang/lang.es.php | 西班牙文语言数据。
/vault/lang/lang.fr.fe.php | 法文前端语言数据。
/vault/lang/lang.fr.php | 法文语言数据。
/vault/lang/lang.id.fe.php | 印度尼西亚文前端语言数据。
/vault/lang/lang.id.php | 印度尼西亚文语言数据。
/vault/lang/lang.it.fe.php | 意大利文前端语言数据。
/vault/lang/lang.it.php | 意大利文语言数据。
/vault/lang/lang.ja.fe.php | 日文前端语言数据。
/vault/lang/lang.ja.php | 日文语言数据。
/vault/lang/lang.ko.fe.php | 韩文前端语言数据。
/vault/lang/lang.ko.php | 韩文语言数据。
/vault/lang/lang.nl.fe.php | 荷兰文前端语言数据。
/vault/lang/lang.nl.php | 荷兰文语言数据。
/vault/lang/lang.pt.fe.php | 葡萄牙文前端语言数据。
/vault/lang/lang.pt.php | 葡萄牙文语言数据。
/vault/lang/lang.ru.fe.php | 俄文前端语言数据。
/vault/lang/lang.ru.php | 俄文语言数据。
/vault/lang/lang.th.fe.php | 泰文前端语言数据。
/vault/lang/lang.th.php | 泰文语言数据。
/vault/lang/lang.ur.fe.php | 乌尔都文前端语言数据。
/vault/lang/lang.ur.php | 乌尔都文语言数据。
/vault/lang/lang.vi.fe.php | 越南文前端语言数据。
/vault/lang/lang.vi.php | 越南文语言数据。
/vault/lang/lang.zh-tw.fe.php | 中文（传统）前端语言数据。
/vault/lang/lang.zh-tw.php | 中文（传统）语言数据。
/vault/lang/lang.zh.fe.php | 中文（简体）前端语言数据。
/vault/lang/lang.zh.php | 中文（简体）语言数据。
/vault/quarantine/ | 隔离文件夹（包含隔离文件）。
/vault/quarantine/.htaccess | 超文本访问文件（在这种情况，以保护敏感文件属于脚本从被访问由非授权来源）。
/vault/signatures/ | 签名文件夹（包含签​​名文件）。
/vault/signatures/.htaccess | 超文本访问文件（在这种情况，以保护敏感文件属于脚本从被访问由非授权来源）。
/vault/signatures/switch.dat | 控制和确定某些变量。
/vault/.htaccess | 超文本访问文件（在这种情况，以保护敏感文件属于脚本从被访问由非授权来源）。
/vault/cli.php | CLI处理文件。
/vault/components.dat | 包含的相关信息关于phpMussel的各种组件；它使用通过更新功能从前端。
/vault/config.ini.RenameMe | 配置文件；包含所有配置指令为phpMussel，告诉它什么做和怎么正确地经营（重命名为激活）。
/vault/config.php | 配置处理文件。
/vault/config.yaml | 配置默认文件；包含phpMussel的默认配置值。
/vault/frontend.php | 前端处理文件。
/vault/functions.php | 功能处理文件（必不可少）。
/vault/greylist.csv | 灰名单签名CSV（逗号分隔变量）文件说明为phpMussel什么签名它应该忽略（文件自动重新创建如果删除）。
/vault/lang.php | 语言数据。
/vault/php5.4.x.php | Polyfill对于PHP 5.4.X （PHP 5.4.X 向下兼容需要它； 较新的版本可以删除它）。
※ /vault/scan_kills.txt | 记录的所有上传文件phpMussel受阻/杀。
※ /vault/scan_log.txt | 记录的一切phpMussel扫描。
※ /vault/scan_log_serialized.txt | 记录的一切phpMussel扫描。
/vault/template_custom.html | 模板文件；模板为HTML产量产生通过phpMussel为它的受阻文件上传信息（信息可见向上传者）。
/vault/template_default.html | 模板文件；模板为HTML产量产生通过phpMussel为它的受阻文件上传信息（信息可见向上传者）。
/vault/themes.dat | 主题文件；它使用通过更新功能从前端。
/vault/upload.php | 上传处理文件。
/.gitattributes | GitHub文件（不需要为正确经营脚本）。
/Changelog-v1.txt | 记录的变化做出至脚本间不同版本（不需要为正确经营脚本）。
/composer.json | Composer/Packagist 信息（不需要为正确经营脚本）。
/CONTRIBUTING.md | 相关信息如何有助于该项目。
/LICENSE.txt | GNU/GPLv2 执照文件（不需要为正确经营脚本）。
/loader.php | 加载文件。这个是文件您应该｢钩子｣（必不可少）!
/PEOPLE.md | 人民卷入到该项目。
/README.md | 项目概要信息。
/web.config | 一个ASP.NET配置文件（在这种情况，以保护`/vault`文件夹从被访问由非授权来源在事件的脚本是安装在服务器根据ASP.NET技术）。

※ 文件名可能不同基于配置规定（在`config.ini`）。

---


### 7. <a name="SECTION7"></a>配置选项
下列是一个列表的变量发现在`config.ini`配置文件的phpMussel，以及一个说明的他们的目的和功能。

#### “general” （类别）
基本phpMussel配置。

“cleanup”
- ｢反设置／删除／清洁｣脚本变量和缓存｢Cache｣之后执行吗？如果您不使用脚本外初始上传扫描，应该设置True｢真/正｣，为了最小化内存使用。如果您使用脚本为目的外初始上传扫描，应该设置False｢假/负｣，为了避免不必要重新加载复制数据在内存。在一般的做法，它应该设置True｢真/正｣，但，如果您做这样，您将不能够使用脚本为任何目的以外文件上传扫描。
- 无影响在CLI模式。

“scan_log”
- 文件为记录在所有扫描结果。指定一个文件名，或留空以关闭。

“scan_log_serialized”
- 文件为记录在所有扫描结果（它采用序列化格式）。指定一个文件名，或留空以关闭。

“scan_kills”
- 文件为记录在所有受阻或已杀上传。指定一个文件名，或留空以关闭。

*有用的建议：如果您想，可以追加日期/时间信息至附加到你的日志文件的名称通过包括这些中的名称： `{yyyy}` 为今年完整， `{yy}` 为今年缩写， `{mm}` 为今月， `{dd}` 为今日， `{hh}` 为今小时。*

*例子：*
- *`scan_log='scan_log.{yyyy}-{mm}-{dd}-{hh}.txt'`*
- *`scan_log_serialized='scan_log_serialized.{yyyy}-{mm}-{dd}-{hh}.txt'`*
- *`scan_kills='scan_kills.{yyyy}-{mm}-{dd}-{hh}.txt'`*

“truncate”
- 截断日志文件当他们达到一定的大小吗？ 值是在B/KB/MB/GB/TB，是日志文件允许的最大大小直到它被截断。 默认值为“0KB”将禁用截断（日志文件可以无限成长）。 注意：适用于单个日志文件！日志文件大小不被算集体的。

“timeOffset”
- 如果您的服务器时间不符合您的本地时间，您可以在这里指定的偏移调整日期/时间信息该产生通过phpMussel根据您的需要。 它一般建议，而不是，调整时区指令的文件`php.ini`，但是有时（例如，当利用有限的共享主机提供商）这并不总是可能做到，所以，此选项在这里是提供。 偏移量是在分钟。
- 例子（添加1小时）： `timeOffset=60`

“timeFormat”
- phpMussel使用的日期符号格式。 标准 = `{Day}, {dd} {Mon} {yyyy} {hh}:{ii}:{ss} {tz}`。

“ipaddr”
- 在哪里可以找到连接请求IP地址？ （可以使用为服务例如Cloudflare和类似）标准是`REMOTE_ADDR`。 警告！不要修改此除非您知道什么您做着！

“enable_plugins”
- 启用phpMussel插件支持吗？ False（假） = 不要启用； True（真） = 要启用【标准】。

“forbid_on_block”
- phpMussel应该发送`403`头随着文件上传受阻信息，或坚持标准`200 OK`？ False（假） = 发送`200`； True（真） = 发送`403`【标准】。

“delete_on_sight”
- 激活的这个指令将指示脚本马上删除任何扫描文件上传匹配任何检测标准，是否通过签名或任何事其他。文件已确定是清洁将会忽略。如果是存档，全存档将会删除，不管如果违规文件是只有一个的几个文件包含在存档。为文件上传扫描，按说，它不必要为您激活这个指令，因为按说，PHP将自动清洗内容的它的缓存当执行是完，意思它将按说删除任何文件上传从它向服务器如果不已移动，复制或删除。这个指令是添加这里为额外安全为任何人谁的PHP副本可能不始终表现在预期方式。 False｢假/负｣：之后扫描，忽略文件【标准】， True｢真/正｣：之后扫描，如果不清洁，马上删除。

“lang”
- 指定标准phpMussel语言。

“quarantine_key”
- phpMussel可以检疫坏文件上传在隔离在phpMussel的安全／保险库｢Vault｣，如果这个是某物您想。 普通用户的phpMussel简单地想保护他们的网站或宿主环境无任何兴趣在深深分析任何尝试文件上传应该离开这个功能关闭，但任何用户有兴趣在更深分析的尝试文件上传为目的恶意软件研究或为类似这样事情应该激活这个功能。检疫的尝试文件上传可以有时还助攻在调试假阳性，如果这个是某物经常发生为您。 以关闭检疫功能，简单地离开`quarantine_key`指令空白，或抹去内容的这个指令如果它不已空白。以激活隔离功能，输入一些值在这个指令。 `quarantine_key`是一个重要安全功能的隔离功能需要以预防检疫功能从成为利用通过潜在攻击者和以预防任何潜在执行的数据存储在检疫。 `quarantine_key`应该被处理在同样方法作为您的密码：更长是更好，和紧紧保护它。为获得最佳效果，在结合使用`delete_on_sight`。

“quarantine_max_filesize”
- 最大允许文件大小为文件在检疫。 文件大于这个指定数值将不成为检疫。 这个指令是重要为使它更难为任何潜在攻击者洪水您的检疫用非通缉数据潜在的造成过度数据用法在您的虚拟主机服务。 标准 = 2MB。

“quarantine_max_usage”
- 最大内存使用允许为检疫。 如果总内存已用通过隔离到达这个数值，最老检疫文件将会删除直到总内存已用不再到达这个数值。 这个指令是重要为使它更难为任何潜在攻击者洪水您的检疫用非通缉数据潜在的造成过度数据用法在您的虚拟主机服务。 标准 = 64MB。

“honeypot_mode”
- 当这个指令（蜜罐模式）是激活，phpMussel将尝试检疫所有文件上传它遇到，无论的如果文件上传是匹配任何包括签名，和没有扫描或分析的那些文件上传将发生。 这个功能应有用为那些想使用的phpMussel为目的病毒或恶意软件研究，但它是不推荐激活这个功能如果预期的用的phpMussel通过用户是为标准文件上传扫描，也不推荐使用蜜罐功能为目的以外蜜罐。 作为标准，这个指令是关闭。 False（假） = 是关闭【标准】； True（真） = 是激活。

“scan_cache_expiry”
- 多长时间应该phpMussel维持扫描结果？ 数值是秒数为维持扫描结果。 标准是21600秒（6小时）； 一个`0`数值将停止维持扫描结果。

“disable_cli”
- 关闭CLI模式吗？CLI模式是按说激活作为标准，但可以有时干扰某些测试工具（例如PHPUnit，为例子）和其他基于CLI应用。 如果您没有需要关闭CLI模式，您应该忽略这个指令。 False（假） = 激活CLI模式【标准】； True（真） = 关闭CLI模式。

“disable_frontend”
- 关闭前端访问吗？前端访问可以使phpMussel更易于管理，但也可能是潜在的安全风险。建议管理phpMussel通过后端只要有可能，但前端访问提供当不可能。保持关闭除非您需要它。 False（假） = 激活前端访问； True（真） = 关闭前端访问【标准】。

“max_login_attempts”
- 最大登录尝试次数（前端）。 标准 = 5。

“FrontEndLog”
- 前端登录尝试的录音文件。指定一个文件名，或留空以禁用。

“disable_webfonts”
- 关闭网络字体吗？ True（真） = 关闭； False（假） = 不关闭【标准】。

#### “signatures” （类别）
签名配置。

“Active”
- 活性签名文件的列表，以逗号分隔。

“fail_silently”
- phpMussel应该报告当签名文件是失踪或损坏吗？ 如果`fail_silently`是关闭，失踪和损坏文件将会报告当扫描，和如果`fail_silently`是激活，失踪和损坏文件将会忽略，有扫描报告为那些文件哪里没有问题。 这个应该按说被留下除非您遇到失败或有其他类似问题。 False（假） = 是关闭； True（真） = 是激活【默认】。

“fail_extensions_silently”
- phpMussel应该报告当扩展是失踪吗？ 如果`fail_extensions_silently`是关闭，失踪扩展将会报告当扫描，和如果`fail_extensions_silently`是激活，失踪扩展将会忽略，有扫描报告为那些文件哪里没有任何问题。 关闭的这个指令可能的可以增加您的安全，但可能还导致一个增加的假阳性。 False（假） = 是关闭； True（真） = 是激活【默认】。

“detect_adware”
- phpMussel应该使用签名为广告软件检测吗？ False（假） = 不检查， True（真） = 检查【默认】。

“detect_joke_hoax”
- phpMussel应该使用签名为病毒／恶意软件笑话／恶作剧检测吗？ False（假） = 不检查， True（真） = 检查【默认】。

“detect_pua_pup”
- phpMussel应该使用签名为PUP/PUA（可能无用／非通缉程序／软件）检测吗？ False（假） = 不检查， True（真） = 检查【默认】。

“detect_packer_packed”
- phpMussel应该使用签名为打包机和打包数据检测吗？ False（假） = 不检查， True（真） = 检查【默认】。

“detect_shell”
- phpMussel应该使用签名为webshell脚本检测吗？ False（假） = 不检查， True（真） = 检查【默认】。

“detect_deface”
- phpMussel应该使用签名为污损和污损软件检测吗？ False（假） = 不检查， True（真） = 检查【默认】。

#### “files” （类别）
文件处理配置。

“max_uploads”
- 最大允许数值的文件为扫描当文件上传扫描之前中止扫描和告诉用户他们是上传太多在同一时间！提供保护针对一个理论攻击哪里一个攻击者尝试DDoS您的系统或CMS通过超载phpMussel以减速PHP进程到一个停止。推荐：10。您可能想增加或减少这个数值，根据速度的您的硬件。注意这个数值不交待为或包括存档内容。

“filesize_limit”
- 文件大小限在KB。 65536 = 64MB 【默认】， 0 = 没有限（始终灰名单）， 任何正数值接受。 这个可以有用当您的PHP配置限内存量一个进程可以占据或如果您的PHP配置限文件大小的上传。

“filesize_response”
- 如何处理文件超过文件大小限（如果存在）。 False（假） = 白名单； True（真） = 黑名单【默认】。

“filetype_whitelist”, “filetype_blacklist”, “filetype_greylist”
- 如果您的系统只允许具体文件类型被上传，或如果您的系统明确地否认某些文件类型，指定那些文件类型在白名单，黑名单和灰名单可以增加扫描执行速度通过允许脚本跳过某些文件类型。格式是CSV（逗号分隔变量）。如果您想扫描一切，而不是白名单，黑名单或灰名单，留变量空；这样做将关闭白名单／黑名单／灰名单。
- 进程逻辑顺序是：
  - 如果文件类型已白名单，不扫描和不受阻文件，和不匹配文件对照黑名单或灰名单。
  - 如果文件类型已黑名单，不扫描文件但阻止它无论如何，和不匹配文件对照灰名单。
  - 如果灰名单是空，或如果灰名单不空和文件类型已灰名单，扫描文件像正常和确定如果阻止它基于扫描结果，但如果灰名单不空和文件类型不灰名单，过程文件仿佛已黑名单，因此不扫描它但阻止它无论如何。

“check_archives”
- 尝试匹配存档内容吗？ False（假） = 不匹配； True（真） = 匹配【默认】。
- 目前，只BZ/BZIP2，GZ/GZIP，LZF，PHAR，TAR和ZIP文件格式是支持（匹配的RAR，CAB，7z和等等不还支持）。
- 这个是不完美！虽说我很推荐保持这个激活，我不能保证它将始终发现一切。
- 还，请注意存档匹配目前是不递归为PHAR或ZIP格式。

“filesize_archives”
- 继承文件大小黑名单／白名单在存档内容吗？ False（假） = 不继承（刚灰名单一切）； True（真） = 继承【默认】。

“filetype_archives”
- 继承文件类型黑名单／白名单在存档内容吗？ False（假） = 不继承（刚灰名单一切）； True（真） = 继承【默认】。

“max_recursion”
- 最大存档递归深度限。 默认 = 10。

“block_encrypted_archives”
- 检测和受阻加密的存档吗？因为phpMussel是不能够扫描加密的存档内容，它是可能存档加密可能的可以使用通过一个攻击者作为一种手段尝试绕过phpMussel，杀毒扫描仪和其他这样的保护。指示phpMussel受阻任何存档它发现被加密可能的可以帮助减少任何风险有关联这些可能性。 False（假） = 不受阻； True（真） = 受阻【默认】。

#### “attack_specific” （类别）
专用攻击指令。

蜴攻击检测： False（假） = 是关闭； True（真） = 是激活。

“chameleon_from_php”
- 寻找PHP头在文件是不PHP文件也不认可存档文件。

“chameleon_from_exe”
- 寻找可执行头在文件是不可执行文件也不认可存档文件和寻找可执行文件谁的头是不正确。

“chameleon_to_archive”
- 寻找存档文件谁的头是不正确（已支持：BZ，GZ，RAR，ZIP，GZ）。

“chameleon_to_doc”
- 寻找办公文档谁的头是不正确（已支持：DOC，DOT，PPS，PPT，XLA，XLS，WIZ）。

“chameleon_to_img”
- 寻找图像谁的头是不正确（已支持：BMP，DIB，PNG，GIF，JPEG，JPG，XCF，PSD，PDD，WEBP）。

“chameleon_to_pdf”
- 寻找PDF文件谁的头是不正确。

“archive_file_extensions”
- 认可存档文件扩展（格式是CSV；应该只添加或去掉当问题发生；不必要的去掉可能的可以导致假阳性出现为存档文件，而不必要的增加将实质上白名单任何事您增加从专用攻击检测；修改有慎重；还请注这个无影响在什么存档可以和不能被分析在内容级）。这个名单，作为是作为标准，名单那些格式使用最常见的横过多数的系统和CMS，但有意是不全面。

“block_control_characters”
- 受阻任何文件包含任何控制字符吗（以外换行符）？ (`[\x00-\x08\x0b\x0c\x0e\x1f\x7f]`) 如果您只上传纯文本，您可以激活这个指令以提供某些另外保护在您的系统。然而，如果您上传任何事以外纯文本，激活这个可能结果在假阳性。 False（假） = 不受阻【默认】； True（真） = 受阻。

“corrupted_exe”
- 损坏文件和处理错误。 False（假） = 忽略； True（真） = 受阻【默认】。 检测和受阻潜在的损坏移植可执行｢PE｣文件吗？时常（但不始终），当某些零件的一个移植可执行｢PE｣文件是损坏或不能被正确处理，它可以建议建议的一个病毒感染。过程使用通过最杀毒程序以检测病毒在PE文件需要处理那些文件在某些方式，哪里，如果程序员的一个病毒是意识的，将特别尝试防止，以允许他们的病毒留不检测。

“decode_threshold”
- 在原始数据中解码命令的长度限制（如果有任何引人注目性能问题当扫描）。 默认 = 512KB。 零或空值将关闭门槛（去除任何这样的限基于文件大小）。

“scannable_threshold”
- 原始数据读取和扫描的最大长度（如果有任何引人注目性能问题当扫描）。 默认 = 32MB。 零或空值将关闭门槛。按说，这个数值应不会少于平均文件大小的文件上传您想和期待收到您的服务器或网站，应不会多于`filesize_limit`指令，和应不会多于大致五分之一的总允许内存分配获授PHP通过`php.ini`配置文件。 这个指令存在为尝试防止phpMussel从用的太多内存（这个将防止它从能够顺利扫描文件以上的一个特别文件大小）。

#### “compatibility” （类别）
phpMussel兼容性指令。

“ignore_upload_errors”
- 这个指令按说应会关闭除非它是需要为对功能的phpMussel在您的具体系统。按说，当是关闭，当phpMussel检测存在元素在`$_FILES`数组，它将尝试引发一个扫描的文件代表通过那些元素，和，如果他们是空或空白，phpMussel将回报一个错误信息。这个是正确行为为phpMussel。然而，为某些CMS，空元素在`$_FILES`可以发生因之的自然的行为的那些CMS，或错误可能会报告当没有任何，在这种情况，正常行为为phpMussel将会使干扰为正常行为的那些CMS。如果这样的一个情况发生为您，激活这个指令将指示phpMussel不尝试引发扫描为这样的空元素，忽略他们当发现和不回报任何关联错误信息，从而允许延续的页面请求。 False（假） = 不忽略； True（真） = 忽略。

“only_allow_images”
- 如果您只期待或只意味到允许图像被上传在您的系统或CMS，和如果您绝对不需要任何文件以外图像被上传在您的系统或CMS，这个指令应会激活，但其他应会关闭。如果这个指令是激活，它将指示phpMussel受阻而不例外任何上传确定为非图像文件，而不扫描他们。这个可能减少处理时间和内存使用为非图像文件上传尝试。 False（假） = 还允许其他文件； True（真） = 只允许图像文件。

#### “heuristic” （类别）
启发式指令。

“threshold”
- 有某些签名的phpMussel意味为确定可疑和可能恶意文件零件被上传有不在他们自己确定那些文件被上传特别是作为恶意。 这个“threshold”数值告诉phpMussel什么是最大总重量的可疑和潜在恶意文件零件被上传允许之前那些文件是被识别作为恶意。 定义的重量在这个上下文是总数值的可疑和可能恶意文件零件确定。 作为默认，这个数值将会设置作为3。一个较低的值通常将结果在一个更高的发生的假阳性但一个更高的发生的恶意文件被确定，而一个更高的数值将通常结果在一个较低的发生的假阳性但一个较低的数值的恶意文件被确定。 它是通常最好忽略这个数值除非您遇到关联问题。

#### “virustotal” （类别）
VirusTotal.com指令。

“vt_public_api_key”
- 可选的，phpMussel可以扫描文件使用｢Virus Total API｣作为一个方法提供一个显着的改善保护级别针对病毒，木马，恶意软件和其他威胁。 作为默认，扫描文件使用｢Virus Total API｣是关闭。 以激活它，一个API密钥从VirusTotal是需要。 因为的显着好处这个可以提供为您，它是某物我很推荐激活。请注意，然而，以使用的｢Virus Total API｣，您必须同意他们的服务条款和您必须坚持所有方针按照说明通过VirusTotal阅读材料！ 您是不允许使用这个积分功能除非：
  - 您已阅读和您同意服务条款的VirusTotal和它的API。 服务条款的VirusTotal和它的API可以发现[这里](https://www.virustotal.com/en/about/terms-of-service/)。
  - 您已阅读和您了解至少序言的VirusTotal公共API阅读材料(一切之后“VirusTotal Public API v2.0”但之前“Contents”）。 VirusTotal公共API阅读材料可以发现[这里](https://www.virustotal.com/en/documentation/public-api/)。

请注意：如果扫描文件使用｢Virus Total API｣是关闭，您不需要修改任何指令在这个类别（`virustotal`），因为没有人将做任何事如果这个是关闭。以获得一个VirusTotalAPI密钥，从随地在他们的网站，点击“加入我们的社区”链接位于朝向右上方的页面，输入在信息请求，和点击“注册”在做完。跟随所有指令提供，和当您有您的公共API密钥，复制／粘贴您的公共API密钥到`vt_public_api_key`指令的`config.ini`配置文件。

“vt_suspicion_level”
- 作为标准，phpMussel将限制什么文件它扫描通过使用｢Virus Total API｣为那些文件它考虑作为“可疑”。 您可以可选调整这个局限性通过修改的`vt_suspicion_level`指令数值。
- `0`:文件是只考虑可疑如果，当被扫描通过phpMussel使用它自己的签名，他们是认为有一个启发式重量。这个将有效意味使用的｢Virus Total API｣将会为一个第二个意见为当phpMussel怀疑一个文件可能的是恶意，但不能完全排除它可能还可能的被良性（非恶意）和因此将否则按说不受阻它或标志它作为被恶意。
- `1`:文件是考虑可疑如果，当被扫描通过phpMussel使用它自己的签名，他们是认为有一个启发式重量，如果他们是已知被可执行（PE文件，Mach-O文件，ELF/Linux文件，等等），或如果他们是已知被的一个格式潜在的包含可执行数据（例如可执行宏，DOC/DOCX文件，存档文件例如RAR格式，ZIP格式和等等）。这个是标准和推荐可疑级别到使用，有效意味使用的｢Virus Total API｣将会为一个第二个意见为当phpMussel不原来发现任何事恶意或错在一个文件它考虑被可疑和因此将否则按说不受阻它或标志它作为被恶意。
- `2`:所有文件是考虑可疑和应会扫描使用｢Virus Total API｣。我通常不推荐应用这个可疑级别，因为风险的到达您的API配额更快，但存在某些情况（例如当网站管理员或主机管理员有很少信仰或信任在任何的内容上传从他们的用户）哪里这个可疑级别可以被适当。有使用的这个可疑级别，所有文件不按说受阻或标志是作为被恶意将会扫描使用｢Virus Total API｣。请注意，然而，phpMussel将停止使用｢Virus Total API｣当您的API配额是到达（无论的可疑级别），和您的配额将会容易更快当使用这个可疑级别。

请注意：无论的可疑级别，任何文件任一已黑名单或已白名单通过phpMussel不会扫描使用｢Virus Total API｣，因为那些文件将会已标志作为恶意或良性通过phpMussel到的时候他们将会否则扫描通过｢Virus Total API｣，和因此，另外扫描不会需要。能力的phpMussel扫描文件使用｢Virus Total API｣是意味为建更置信为如果一个文件是恶意或良性在那些情况哪里phpMussel是不完全确定如果一个文件是恶意或良性。

“vt_weighting”
- phpMussel应使用扫描结果使用｢Virus Total API｣作为检测或作为检测重量吗？ 这个指令存在，因为，虽说扫描一个文件使用多AV引擎（例如怎么样VirusTotal做） 应结果有一个增加检测率（和因此在一个更恶意文件被抓）， 它可以还结果有更假阳性，和因此，为某些情况，扫描结果可能被更好使用作为一个置信得分而不是作为一个明确结论。 如果一个数值的`0`是使用，扫描结果使用｢Virus Total API｣将会适用作为检测，和因此，如果任何AV引擎使用通过VirusTotal标志文件被扫描作为恶意， phpMussel将考虑文件作为恶意。 如果任何其他数值是使用，扫描结果使用｢Virus Total API｣将会适用作为检测重量，和因此，数的AV引擎使用通过VirusTotal标志文件被扫描作为恶意将服务作为一个置信得分（或检测重量） 为如果文件被扫描应会考虑恶意通过phpMussel（数值使用将代表最低限度的置信得分或重量需要以被考虑恶意）。 一个数值的`0`是使用作为标准。

“vt_quota_rate”和“vt_quota_time”
- 根据｢Virus Total API｣阅读材料，它是限于最大的`4`请求的任何类型在任何`1`分钟大体时间。 如果您经营一个“honeyclient”， 蜜罐或任何其他自动化将会提供资源为VirusTotal和不只取回报告您是有权一个更高请求率配额。 作为标准，phpMussel将严格的坚持这些限制，但因为可能性的这些率配额被增加，这些二指令是提供为您指示phpMussel为什么限它应坚持。 除非您是指示这样做，它是不推荐为您增加这些数值，但，如果您遇到问题相关的到达您的率配额，减少这些数值可能有时帮助您解析这些问题。 您的率限是决定作为`vt_quota_rate`请求的任何类型在任何`vt_quota_time`分钟大体时间。

#### “urlscanner” （类别）
phpMussel包含URL扫描程序，能够检测恶意URL在任何数据或文件它扫描。

请注意：如果URL扫描仪已关闭，您将不需要复习任何指令在这个类别（`urlscanner`），因为没有指令会做任何事如果这个已关闭。

URL扫描仪API配置。

“lookup_hphosts”
- 激活[hpHosts](http://hosts-file.net/) API当设置`true`。 hpHosts不需要API密钥为了执行API请求。

“google_api_key”
- 激活Google Safe Browsing API当API密钥是设置。 Google Safe Browsing API需要API密钥，可以得到从[这里](https://console.developers.google.com/)。
- 请注意：cURL扩展是必须的为了使用此功能。

“maximum_api_lookups”
- 最大数值API请求来执行每个扫描迭代。 额外API请求将增加的总要求完成时间每扫描迭代，所以，您可能想来规定一个限以加快全扫描过程。 当设置`0`，没有最大数值将会应用的。 设置`10`作为默认。

“maximum_api_lookups_response”
- 该什么办如果最大数值API请求已超过？ False（假） = 没做任何事（继续处理） 【默认】； True（真） = 标志/受阻文件。

“cache_time”
- 多长时间（以秒为单位）应API结果被缓存？ 默认是3600秒（1小时）。

#### “template_data” （类别）
指令和变量为模板和主题。

模板数据涉及到HTML产量使用以生成“上传是否认”信息显示为用户当一个文件上传是受阻。如果您使用个性化主题为phpMussel，HTML产量资源是从`template_custom.html`文件，和否则，HTML产量资源是从`template.html`文件。变量书面在这个配置文件部分是喂在HTML产量通过更换任何变量名包围在大括号发现在HTML产量使用相应变量数据。为例子，哪里`foo="bar"`，任何发生的`<p>{foo}</p>`发现在HTML产量将成为`<p>bar</p>`。

“theme”
- 用于phpMussel的默认主题。

“css_url”
- 模板文件为个性化主题使用外部CSS属性，而模板文件为t标准主题使用内部CSS属性。 以指示phpMussel使用模板文件为个性化主题，指定公共HTTP地址的您的个性化主题的CSS文件使用`css_url`变量。 如果您离开这个变量空白，phpMussel将使用模板文件为默认主题。

---


### 8. <a name="SECTION8"></a>签名格式

#### *文件名签名*
所有文件名签名跟随格式：

`NAME:FNRX`

`NAME`是名援引为签名和`FNRX`是正则表达式匹配文件名（未编码）为。

#### *MD5签名*
所有MD5签名跟随格式：

`HASH:FILESIZE:NAME`

`HASH`是一个MD5哈希的一个全文件，`FILESIZE`是总文件大小和`NAME`是名援引为签名。

#### *移植可执行｢PE｣部分签名*
所有移植可执行｢PE｣部分签名跟随格式：

`SIZE:HASH:NAME`

`HASH`是一个MD5哈希的一个部分的一个移植可执行｢PE｣文件，`SIZE`是总大小的该部分和`NAME`是名援引为签名。

#### *移植可执行｢PE｣扩展签名*
所有移植可执行｢PE｣扩展签名跟随格式：

`$VAR:HASH:SIZE:NAME`

`$VAR`是移植可执行｢PE｣变量名匹配为，`HASH`是一个MD5哈希的该变量，`SIZE`是总大小的该变量和`NAME`是名援引为签名。

#### *白名单签名*
所有白名单签名跟随格式：

`HASH:FILESIZE:TYPE`

`HASH`是MD5哈希的一个全文件，`FILESIZE`是总文件大小和`TYPE`是签名类型为白名单文件成为免疫的针对。

#### *复杂扩展签名*
复杂扩展签名是宁不同从其他可能phpMussel签名类型，在某种意义上说，什么他们匹配针对是指定通过这些签名他们自己和他们可以匹配针对多重标准。多重标准是分隔通过【;】和匹配类型和匹配数据的每多重标准是分隔通过【:】以确保格式为这些签名往往看起来有点像：

`$变量1:某些数据;$变量2:某些数据;签名等等`

#### *一切其他*
所有其他签名跟随格式：

`NAME:HEX:FROM:TO`

`NAME`是名援引为签名和`HEX`是一个十六进制编码分割的文件意味被匹配通过有关签名。`FROM`和`TO`是可选参数，说明从哪里和向哪里在源数据匹配针对。

#### *正则表达式／REGEX*
任何形式的正则表达式了解和正确地处理通过PHP应还会正确地了解和处理通过phpMussel和它的签名。然而，我将建议采取极端谨慎当写作新正则表达式为基础的签名，因为，如果您不完全肯定什么您被做，可以有很不规则和／或意外结果。看一眼的phpMussel源代码如果您不完全肯定的上下文其中正则表达式语句被处理。还，记得，所有语句（除外为文件名，存档元数据和MD5语句）必须是十六进制编码（和除外为语句句法，还，当然）！

#### *哪里放个性化签名*
放个性化签名只在那些文件意味为个性化签名。那些文件应包含`_custom`在他们的文件名。您应还避免修改的标准签名文件，除非您确切地知什么您被做，因为，除了大体被好习惯和除了帮助您区分间您自己的签名和标准签名包括在phpMussel，它是好依照只修改文件意味为修改，因为篡改标准签名文件可以导致他们停止正确地运作，因为MAP｢地图｣（`.map`）文件：这些文件告诉phpMussel哪里在签名文件到定位签名需要通过phpMussel按照当需要，和这些MAP｢地图｣文件可以成为不同步从他们的关联签名文件如果那些签名文件是篡改。您可以放几乎任何您想在您的个性化签名，只要您跟随对句法。然而，当心和测试新签名为假阳性预如果您意味共享他们或使用他们在一个活环境。

---


### 9. <a name="SECTION9"></a>已知的兼容问题

#### PHP和PCRE
- phpMussel需要PHP和PCRE以正确地执行和功能。如果没有PHP，或如果没有PCRE扩展的PHP，phpMussel不会正确地执行和功能。应该确保您的系统有PHP和PCRE安装和可用之前下载和安装phpMussel。

#### 杀毒软件兼容性

在大多数情况下，phpMussel应该相当兼容性与大多数杀毒软件。然，冲突已经报道由多个用户以往。下面这些信息是从VirusTotal.com，和它描述了一个数的假阳性报告的各种杀毒软件针对phpMussel。虽说这个信息是不绝对保证的如果您会遇到兼容性问题间phpMussel和您的杀毒软件，如果您的杀毒软件注意冲突针对phpMussel，您应该考虑关闭它之前使用phpMussel或您应该考虑替代选项从您的杀毒软件或从phpMussel。

这个信息最后更新2016年8月29日和是准确为至少phpMussel的两个最近次要版本（v0.10.0-v1.0.0）在这个现在时候的写作。

| 扫描器               |  结果                                 |
|----------------------|--------------------------------------|
| Ad-Aware             |  无冲突 |
| AegisLab             |  无冲突 |
| Agnitum              |  无冲突 |
| AhnLab-V3            |  无冲突 |
| Alibaba              |  无冲突 |
| ALYac                |  无冲突 |
| AntiVir              |  无冲突 |
| Antiy-AVL            |  无冲突 |
| Arcabit              |  无冲突 |
| Avast                |  报告 "JS:ScriptSH-inf [Trj]" |
| AVG                  |  无冲突 |
| Avira                |  无冲突 |
| AVware               |  无冲突 |
| Baidu                |  报告 "VBS.Trojan.VBSWG.a" |
| Baidu-International  |  无冲突 |
| BitDefender          |  无冲突 |
| Bkav                 |  报告 "VEXC640.Webshell"， "VEXD737.Webshell"， "VEX5824.Webshell"， "VEXEFFC.Webshell"|
| ByteHero             |  无冲突 |
| CAT-QuickHeal        |  无冲突 |
| ClamAV               |  无冲突 |
| CMC                  |  无冲突 |
| Commtouch            |  无冲突 |
| Comodo               |  无冲突 |
| Cyren                |  无冲突 |
| DrWeb                |  无冲突 |
| Emsisoft             |  无冲突 |
| ESET-NOD32           |  无冲突 |
| F-Prot               |  无冲突 |
| F-Secure             |  无冲突 |
| Fortinet             |  无冲突 |
| GData                |  无冲突 |
| Ikarus               |  无冲突 |
| Jiangmin             |  无冲突 |
| K7AntiVirus          |  无冲突 |
| K7GW                 |  无冲突 |
| Kaspersky            |  无冲突 |
| Kingsoft             |  无冲突 |
| Malwarebytes         |  无冲突 |
| McAfee               |  报告 "New Script.c" |
| McAfee-GW-Edition    |  报告 "New Script.c" |
| Microsoft            |  无冲突 |
| MicroWorld-eScan     |  无冲突 |
| NANO-Antivirus       |  无冲突 |
| Norman               |  无冲突 |
| nProtect             |  无冲突 |
| Panda                |  无冲突 |
| Qihoo-360            |  无冲突 |
| Rising               |  无冲突 |
| Sophos               |  无冲突 |
| SUPERAntiSpyware     |  无冲突 |
| Symantec             |  无冲突 |
| Tencent              |  无冲突 |
| TheHacker            |  无冲突 |
| TotalDefense         |  无冲突 |
| TrendMicro           |  无冲突 |
| TrendMicro-HouseCall |  无冲突 |
| VBA32                |  无冲突 |
| VIPRE                |  无冲突 |
| ViRobot              |  无冲突 |
| Zillya               |  无冲突 |
| Zoner                |  无冲突 |

---


### 10. <a name="SECTION10"></a>常见问题（FAQ）

#### 什么是“签名”？

In the context of phpMussel, a "signature" refers to data that acts as an indicator/identifier for something specific that we're looking for, usually in the form of some very small, distinct, innocuous segment of something larger and otherwise harmful, like a virus or trojan, or in the form of a file checksum, hash, or other similarly identifying indicator, and usually includes a label, and some other data to help provide additional context that can be used by phpMussel to determine the best way to proceed when it encounters what we're looking for.

#### 什么是“假阳性”？

术语“假阳性”（*或者：“假阳性错误”；“虚惊”*；英语：*false positive*; *false positive error*; *false alarm*），很简单地描述，和在一个广义上下文，被用来当测试一个因子，作为参考的测试结果，当结果是阳性（即：因子被确定为“阳性”，或“真”），但预计将为（或者应该是）阴性（即：因子，在现实中，是“阴性”，或“假”）。一个“假阳性”可被认为是同样的“哭狼” (其中，因子被测试是是否有狼靠近牛群，因子是“假”由于该有没有狼靠近牛群，和因子是报告为“阳性”由牧羊人通过叫喊“狼，狼”），或类似在医学检测情况，当患者被诊断有一些疾病，当在现实中，他们没有疾病。

一些相关术语是“真阳性”，“真阴性”和“假阴性”。一个“真阳性”指的是当测试结果和真实因子状态都是“真”（或“阳性”），和一个“真阴性”指的是当测试结果和真实因子状态都是“假”（或“阴性”）；一个“真阳性”或“真阴性”被认为是一个“正确的推理”。对立面“假阳性”是一个“假阴性”；一个“假阴性”指的是当测试结果是“阴性”（即：因子被确定为“阴性”，或“假”），但预计将为（或者应该是）阳性（即：因子，在现实中，是“阳性”，或“真”）。

在phpMussel的上下文，这些术语指的是phpMussel的签名和他们阻止的文件。当phpMussel阻止一个文件由于恶劣的，过时的，或不正确的签名，但不应该这样做，或当它这样做为错误的原因，我们将此事件作为一个“假阳性”。当phpMussel未能阻止文件该应该已被阻止，由于不可预见的威胁，缺少签名或不足签名，我们将此事件作为一个“检测错过”（同样的“假阴性”）。

这可以通过下表来概括：

&nbsp; | phpMussel不应该阻止文件 | phpMussel应该阻止文件
---|---|---
phpMussel不会阻止文件 | 真阴性（正确的推理） | 检测错过（同样的“假阴性”）
phpMussel会阻止文件 | __假阳性__ | 真阳性（正确的推理）

#### 什么是签名更新频率？

更新频率根据相关的签名文件而有所不同。所有的phpMussel签名文件的维护者通常尽量保持他们的签名为最新，但是因为我们所有人都有各种其他承诺，和因为我们的生活超越了项目，和因为我们不得到经济补偿/付款为我们的项目的努力，无法保证精确的更新时间表。通常，签名被更新每当有足够的时间，和维护者尝试根据必要性和根据范围之间的变化频率确定优先级。帮助总是感谢，如果你愿意提供任何。

#### 我在使用phpMussel时遇到问题和我不知道该怎么办！ 请帮忙！

- 您使用软件的最新版本吗？您使用签名文件的最新版本吗？如果这两个问题的答案是不，尝试首先更新一切，然后检查问题是否仍然存在。如果它仍然存在，继续阅读。
- 您检查过所有的文档吗？如果没有做，请这样做。如果文档不能解决问题，继续阅读。
- 您检查过 **[问题页面](https://github.com/Maikuolan/phpMussel/issues)** 吗？ 检查是否已经提到了问题。如果已经提到了，请检查是否提供了任何建议，想法或解决方案。按照需要尝试解决问题。
- 您检查过 **[由Spambot Security提供的phpMussel支持论坛](http://www.spambotsecurity.com/forum/viewforum.php?f=55)** 吗？ 检查是否已经提到了问题。如果已经提到了，请检查是否提供了任何建议，想法或解决方案。按照需要尝试解决问题。
- 如果问题仍然存在，请让我们知道；在问题页面或支持论坛上开始新的讨论。

#### 我想使用phpMussel与早于5.4.0的PHP版本； 您能帮我吗？

不能。PHP 5.4.0于2014年达到官方EoL（“生命终止”）。延长的安全支持在2015年终止。这时候目前，它是2017年，和PHP 7.1.0已经可用。目前，有支持使用phpMussel与PHP 5.4.0和所有可用的较新的PHP版本，但不有支持使用phpMussel与任何以前的PHP版本。

#### 我可以使用单个phpMussel安装来保护多个域吗？

可以。phpMussel安装未绑定到特定域，因此可以用来保护多个域。通常，当phpMussel安装保护只一个域，我们称之为“单域安装”，和当phpMussel安装保护多个域和/或子域，我们称之为“多域安装”。如果您进行多域安装并需要使用不同的签名文件为不同的域，或需要不同配置phpMussel为不同的域，这可以做到。加载配置文件后（`config.ini`），phpMussel将寻找“配置覆盖文件”特定于所请求的域（`xn--cjs74vvlieukn40a.tld.config.ini`），并如果发现，由配置覆盖文件定义的任何配置值将用于执行实例而不是由配置文件定义的配置值。配置覆盖文件与配置文件相同，并通过您的决定，可能包含phpMussel可用的所有配置指令，或任何必需的部分当需要。配置覆盖文件根据它们旨在的域来命名（所以，例如，如果您需要一个配置覆盖文件为域，`http://www.some-domain.tld/`，它的配置覆盖文件应该被命名`some-domain.tld.config.ini`，和它应该放置在`vault`与配置文件，`config.ini`）。域名是从标题`HTTP_HOST`派生的；“www”被忽略。

---


最后更新：2017年5月19日。

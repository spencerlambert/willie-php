<?php
//exit;
    // disable magic quotes
    if (get_magic_quotes_gpc() && !defined('MAGIC_QUOTES_STRIPPED')){
        $_POST = array_map('stripslashes',$_POST);
    }
 
    if(!$_POST['db_host']) $_POST['db_host'] = 'localhost';






// generate gallery script

    



?>
<html>
<head>
    <title>MySimple</title>
    <style type="text/css">
        body {
            font: 90% sans-serif;
        }
 
        table {
            font: 90% sans-serif;
            border-right: 1px solid #000;
            border-top: 1px solid #000;
            empty-cells: show;
        }
        td, th {
            border-left: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 0.2em 0.5em;
        }
        input, textarea, fieldset {
            border: 1px solid #333;
        }
    </style>
</head>
<body>
    <form action="" method="post">
    <fieldset>
        <legend>Database Connection</legend>
 
    </fieldset>
 
    <fieldset>
        <legend>Query</legend>
        <textarea name="query" style="width: 98%" rows="20"><?php echo htmlspecialchars($_POST['query'])?></textarea><br />
 
        <input type="submit" name="go" style="cursor: hand" /> (separate multiple queries with a semicolon at end of line)
    </fieldset>
 
    <?php
    // do the work
    if($_POST['go']){
        $ok = true;
        echo '<fieldset><legend>Results</legend>';
 
        // connect to db host
        $link = @mysql_connect("localhost", "root", "eLs862paX7");
        if(!$link){
            echo "<b>Could not connect: ".mysql_error()."</b><br />";
            $ok = false;
        }else{
            echo "<b>Connected to host</b><br />";
        }
 
        // select database
        if($ok){
            if($_POST['db_name']){
                if(!@mysql_select_db($_POST['db_name'])){
                    echo "<b>Could not select DB: ".mysql_error()."</b><br />";
                    $ok = false;
                }else{
                    echo "<b>Database selected</b><br />";
                }
            }
        }
 
        // run queries
        if($ok){
            if($_POST['query']){
                $queries = preg_split("/;(\r\n|\r|\n)/s",$_POST['query']);
                $queries = array_filter($queries);
 
                foreach($queries as $query){
                    echo '<hr >';
                    $result = @mysql_query($query);
                    if(!$result){
                        echo "<b>Query failed: ".mysql_error()."</b><br /><pre>".htmlspecialchars($query)."</pre><br />";
                    }else{
                        echo '<b>'.mysql_affected_rows($link).' affected rows</b><br />';
 
                        if($result != 1){
                            echo '<table cellpadding="0" cellspacing="0">'."\n";
                            $first = true;
                            while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
                                if($first){
                                    echo "\t<tr>\n";
                                    foreach (array_keys($line) as $col_value) {
                                        echo "\t\t<th>".htmlspecialchars($col_value)."</th>\n";
                                    }
                                    echo "\t</tr>\n";
                                    $first = false;
                                }
                                echo "\t<tr>\n";
                                foreach ($line as $col_value) {
                                    echo "\t\t<td>".htmlspecialchars($col_value)."</td>\n";
                                }
                                echo "\t</tr>\n";
                            }
                            echo "</table>\n";
                        }
                    }
                }
            }
        }
 
        echo '</pre></fieldset>';
    }
    ?>
    </form>
</body>
</html>

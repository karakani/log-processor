<?xml version="1.0" encoding="Shift_JIS"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:svg="http://www.w3.org/2000/svg"
                xmlns="http://www.w3.org/1999/xhtml"
                version="1.0">
    <xsl:output method="html" encoding="UTF-8"/>

    <xsl:template match="/">
        <xsl:apply-templates/>
    </xsl:template>

    <xsl:template match="archive">
        <html>
            <head>
                <style type="text/css">
                    #summary {
                    font-size: 10px;
                    margin-top: 15px;
                    }
                    #console {
                    font-size: 10px;
                    }
                    .legend {
                    font-size: 10px;
                    }
                    #console {
                    background-color: #EEE;
                    }
                    line {
                    stroke: rgb(255,0,0);
                    }
                    line.SELECT {
                    stroke: rgb(128,128,255);
                    }
                    line.INSERT {
                    stroke: rgb(255,0,255);
                    }
                    line.UPDATE {
                    stroke: rgb(0,255,0);
                    }
                    line.DELETE {
                    stroke: rgb(0,255,255);
                    }
                    line.DESCRIBE {
                    stroke: rgb(255,128,128);
                    }
                </style>
            </head>
            <body style="margin: 0; padding: 0; position: absolute; width: 100%; height: 100%;">
                <div style="position: absolute; top: 0; left: 0; right: 0;">
                    <table id="summary">
                        <tbody>
                            <tr>
                                <th>Begins at</th>
                                <td>
                                    <xsl:value-of select="meta/@begin"/>
                                </td>
                            </tr>
                            <tr>
                                <th>Ends at</th>
                                <td>
                                    <xsl:value-of select="meta/@end"/>
                                </td>
                            </tr>
                            <tr>
                                <th>Entries</th>
                                <td>
                                    <xsl:value-of select="count(logs/log)"/>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <pre id="console"></pre>
                </div>
                <div class="legend"
                     style="position: absolute; top: 0; left: 0; right: 0; height: 20px; overflow: hidden;" id="legend">
                    <xsl:call-template name="legend_x">
                        <xsl:with-param name="base" select="meta/@begin"/>
                        <xsl:with-param name="n" select="(meta/@end - meta/@begin) div 60"/>
                    </xsl:call-template>
                </div>
                <div style="position: absolute; left: 0; top: 0; right: 0; bottom: 0; overflow: scroll;" id="canvas">
                    <xsl:element name="svg:svg">
                        <xsl:attribute name="height">
                            <xsl:value-of select="count(logs/log)"/>
                        </xsl:attribute>
                        <xsl:attribute name="width">
                            <xsl:value-of select="(meta/@end - meta/@begin) div 1"/>
                        </xsl:attribute>
                        <xsl:attribute name="version">1.1</xsl:attribute>

                        <!-- print axis -->
                        <xsl:call-template name="horizontal_bar">
                            <xsl:with-param name="n" select="(meta/@end - meta/@begin) div 60"/>
                            <xsl:with-param name="height" select="count(logs/log)"/>
                        </xsl:call-template>

                        <xsl:apply-templates select="logs/log">
                            <xsl:with-param name="offset">
                                <xsl:value-of select="meta/@begin"/>
                            </xsl:with-param>
                            <xsl:with-param name="scale">1</xsl:with-param>
                        </xsl:apply-templates>
                    </xsl:element>
                </div>
                <script>
                    var legend = document.getElementById('legend');
                    var console = document.getElementById('console');
                    document.getElementById('canvas')
                            .addEventListener('scroll', function (e) {
                                legend.style.left = (e.target.scrollLeft * -1) + 'px';
                            });
                    function showDetail(node) {
                    console.innerHTML = node.getAttribute('data-table') + '&lt;br/>' + node.getAttribute('data-command')
                    + '&lt;br/>' + node.getAttribute('data-sql');
                    }
                </script>
            </body>
        </html>
    </xsl:template>

    <xsl:template match="log">
        <xsl:param name="offset"/>
        <xsl:param name="scale"/>
        <xsl:element name="svg:line">
            <xsl:attribute name="x1">
                <xsl:value-of select="(@start - $offset) div $scale"/>
            </xsl:attribute>
            <xsl:attribute name="y1">
                <xsl:value-of select="position()"/>
            </xsl:attribute>
            <xsl:attribute name="x2">
                <xsl:value-of select="(@end - $offset) div $scale"/>
            </xsl:attribute>
            <xsl:attribute name="y2">
                <xsl:value-of select="position()"/>
            </xsl:attribute>
            <xsl:attribute name="style">stroke-width:1</xsl:attribute>
            <xsl:attribute name="data-lock-time">
                <xsl:value-of select="params/@Lock_time"/>
            </xsl:attribute>
            <xsl:attribute name="data-sql">
                <xsl:value-of select="params"/>
            </xsl:attribute>
            <xsl:attribute name="data-table">
                <xsl:value-of select="@table"/>
            </xsl:attribute>
            <xsl:attribute name="data-command">
                <xsl:value-of select="@command"/>
            </xsl:attribute>
            <xsl:attribute name="class">
                <xsl:value-of select="@table"/><xsl:text> </xsl:text><xsl:value-of select="@command"/>
            </xsl:attribute>
            <xsl:attribute name="onmouseover">showDetail(this)</xsl:attribute>
        </xsl:element>
    </xsl:template>

    <xsl:template name="horizontal_bar">
        <xsl:param name="n"/>
        <xsl:param name="height"/>
        <xsl:param name="i" select="0"/>

        <xsl:element name="svg:line">
            <xsl:attribute name="x1">
                <xsl:value-of select="$i * 60"/>
            </xsl:attribute>
            <xsl:attribute name="y1">
                <xsl:value-of select="0"/>
            </xsl:attribute>
            <xsl:attribute name="x2">
                <xsl:value-of select="$i * 60"/>
            </xsl:attribute>
            <xsl:attribute name="y2">
                <xsl:value-of select="$height"/>
            </xsl:attribute>
            <xsl:attribute name="style">stroke:rgb(200,200,200);stroke-width:0.5</xsl:attribute>
        </xsl:element>

        <xsl:if test="$n > $i">
            <xsl:call-template name="horizontal_bar">
                <xsl:with-param name="height" select="$height"/>
                <xsl:with-param name="n" select="$n"/>
                <xsl:with-param name="i" select="$i + 1"/>
            </xsl:call-template>
        </xsl:if>
    </xsl:template>

    <xsl:template name="legend_x">
        <xsl:param name="base"/>
        <xsl:param name="n"/>
        <xsl:param name="i" select="0"/>

        <xsl:element name="div">
            <xsl:attribute name="style">
                position: absolute;
                left: <xsl:value-of select="$i * 60"/>px;
            </xsl:attribute>
            <xsl:value-of select="format-number(floor($i div 60), '00')"/>:<xsl:value-of
                select="format-number(($i mod 60), '00')"/>
        </xsl:element>

        <xsl:if test="$n > $i">
            <xsl:call-template name="legend_x">
                <xsl:with-param name="n" select="$n"/>
                <xsl:with-param name="base" select="$base"/>
                <xsl:with-param name="i" select="$i + 1"/>
            </xsl:call-template>
        </xsl:if>
    </xsl:template>

</xsl:stylesheet>
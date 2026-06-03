<?xml version="1.0" encoding="UTF-8"?>
<schema xmlns="http://purl.oclc.org/dsdl/schematron" queryBinding="xslt2"
    xmlns:sqf="http://www.schematron-quickfix.com/validator/process">
    <ns uri="http://www.tei-c.org/ns/1.0" prefix="tei"/>
    <pattern>
        <!-- Change the attribute to point the element being the context of the assert expression. -->
        <rule context="tei:ref">
            <!-- Change the assert expression. -->
            <assert test="starts-with(@target,'#')">Missing # in ref/@target.</assert>
            <assert test="starts-with(@target,'#vat-gr-754:')
                or starts-with(@target,'#vat-gr-1422:')
                or starts-with(@target,'#coislin-10:')
                or starts-with(@target,'#coislin-12:')
                or starts-with(@target,'#coislin-187:')
                or starts-with(@target,'#ambr-b-106-sup:')
                or starts-with(@target,'#ambr-m-47-sup:')
                or starts-with(@target,'#bodl-auct-d-4-1:')
                or starts-with(@target,'#oxon-s-trin-78:')
                or starts-with(@target,'#par-gr-139:')
                or starts-with(@target,'#par-gr-164')
                or starts-with(@target,'#par-gr-166:')
                or starts-with(@target,'#mosq-syn-194:')
                or starts-with(@target,'#franzon-3:')
                or starts-with(@target,'#laudon-gr-42:')
                or starts-with(@target,'#plut-5-30')
                or starts-with(@target,'#plut-6-3:')">Wrong prefix in ref/@target</assert>
        </rule>
    </pattern>
</schema>
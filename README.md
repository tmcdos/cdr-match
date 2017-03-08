# CDR matcher

### Simple web interface for mapping CDR from VoIP provider to Asterisk ###

----------
This is a simple tool to map information for the cost of phone calls given by your VoIP provider, to the call log inside your Asterisk.
As a result, you will know how much each of your employees has spent for phone calls.

In order to use this tool, please follow these steps:

1. add a column COST of type DOUBLE to the table CDR in database AsteriskCDRdb of your Asterisk VoIP appliance;
2. add a column USER_ID of type MEDIUMINT to the table CDR in database AsteriskCDRdb of your Asterisk VoIP appliance;
3. add a column TEL_INT of type SMALLINT to the table USERS in your ERP software (it stores the assigned extension phone number)
4. add a column DEPART_ID of type SMALLINT to the table USERS in your ERP software (it shows the current department of this employee)
5. create a table DEPARTMENT with 2 columns - ID (autoincrement) and DEPARTMENT (name of the department) and populate it with your departments

Additionally, this tool shows information about the assigned DID and CLIP numbers in Asterisk. This is helpful to identify obsolete, duplicate and multiple assignments.
- DID number is the public phone number which your clients use to call a specific employee in your office (Direct In-line Dialing)
- CLIP number is the number your clients see as a caller ID when your employees make outgoing phone calls (Calling Line Identification Presentation)

Note:
You will certainly have to adjust PHP code for importing CSV files - your VoIP provider will not necessarily use the same order of columns as mine, or format the values in the same way.

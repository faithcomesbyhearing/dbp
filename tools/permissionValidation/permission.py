import mysql.connector
from mysql.connector import errorcode
import pprint
from urllib.request import urlopen
import json
import xlrd
import csv
import config
from myconfig import *
# you need to add a myconfig.py file

singlekey = 'somekey';
apiUrl = 'http://api.dbp.test:80/api/';


def keyTest(mykey):

    try:
        cnx = mysql.connector.connect(**myconfig)

    except mysql.connector.Error as err:
        if err.errno == errorcode.ER_ACCESS_DENIED_ERROR:
                print("Something is wrong with your user name or password")
        elif err.errno == errorcode.ER_BAD_DB_ERROR:
                print("Database does not exist")
        else:
                print(err)
    else:


        cursor = cnx.cursor()

        query = ("SELECT agak.access_group_id, bf.id, bf.asset_id, bf.set_type_code, bf.set_size_code "
                "FROM access_group_filesets agf "
                "JOIN bible_filesets bf ON agf.hash_id=bf.hash_id "
                "JOIN dbp_users.access_group_api_keys agak ON agak.access_group_id = agf.access_group_id "
                "JOIN dbp_users.user_keys uk ON agak.key_id=uk.id "
                "WHERE uk.key IN ('" + mykey + "') "
                "ORDER BY bf.id")

        print(query)

        cursor.execute(query)
        myresult = cursor.fetchall()

        print(myresult)
    
        return myresult
        cursor.close()


    cnx.close()

def getFlattenedApi(mykey):
    response = urlopen(apiUrl + 'bibles?key='+ mykey + '&v=4')
    apiResult = json.load(response)
    newApi = []
    #print(apiResult)
    for el in apiResult['data']:
        
        if el['filesets']['dbp-prod']:
            for fs in el['filesets']['dbp-prod']:
                fs['asset'] = 'dbp-prod'
                newApi.append(fs)

    #print(newApi)
    print(len(newApi))
    return newApi


def compareFilesets(mykey):
    
    apiList = getFlattenedApi(mykey)
    apiNum = len(apiList)

    #fmtApiResult = json.dumps(apiResult, indent=2)

    dbResult = keyTest(mykey);
    #print(dbResult)
    dbFilesets = len(dbResult)

    if apiNum == dbFilesets:
        print(mykey + ' has the same number of filesets ' + str(apiNum))
    else:
        print(mykey + ' filesets returned by api: ' + str(apiNum))
        print(mykey + ' filesets returned by db: ' + str(dbFilesets))


    with open('permissions-testkey-101-db.csv', 'w', newline='') as csvfile:
        writer = csv.writer(csvfile)
        writer.writerow(['access_group_id', 'id', 'asset_id', 'set_type_code', 'set_type_size', 'found_in_api'])
        
        for dbRow in dbResult:

            for apiEl in apiList:
                dbRowList = list(dbRow)
                if (apiEl['id'][0:6] == dbRow[1][0:6] and apiEl['asset'] == dbRow[2] and apiEl['type'] == dbRow[3] and apiEl['size'] == dbRow[4]):
                    dbRowList.extend(['yes'])
                    #print(dbRowList)
                    #print(apiEl)
                else:
                    dbRowList.extend(['no']) 
                    

            writer.writerow(dbRowList)
            #print(dbRowList)
        
  
# iterate thru spreadsheet keys
loc = ("permissionTest.xlsx") 
wb = xlrd.open_workbook(loc) 
sheet = wb.sheet_by_index(0) 
 


columns = sheet.col(1)
columns.pop(0)#removed heading


for keyval in columns:
    mykey = keyval.value
#    print('key ' + mykey)
#    compareFilesets(mykey)
compareFilesets(singlekey)
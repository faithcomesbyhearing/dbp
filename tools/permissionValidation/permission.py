import sys
import mysql.connector
from mysql.connector import errorcode
import pprint
import urllib
from urllib.request import urlopen
import json
import os
import errno
import csv
from myconfig import *
# you need to add a myconfig.py file with contents similar to this
#local
# myconfig = {
#   'user': xxx,
#   'password': xxx,
#   'host': '127.0.0.1',
#   'port': '3306',
#   'database': 'dbp_201014',
#   'raise_on_warnings': True
# }

singlekey = 'testkey-165'
apiUrl = 'http://api.dbp.test:80/api/'

class fileset:
      def __init__(self, source, access_group_id, fileset_id, media, contentType, size):
            self.source = source
            self.access_group_id = access_group_id
            self.fileset_id = fileset_id
            self.media = media
            self.contentType = contentType
            self.size = size

      def __str__(self):
            from pprint import pprint
            return str(vars(self))

      def toJson(self):
            return json.dumps(self, default=lambda o: o.__dict__)     

      def __eq__(self, other):
             return self.access_group_id==other.access_group_id and self.fileset_id==other.fileset_id and self.media==other.media \
                and self.contentType==other.contentType and self.size== other.size

def Diff(li1, li2):
    li_dif = [i for i in li1 + li2 if i not in li1 or i not in li2]
    return li_dif

def getDatabaseFilesets(mykey):

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

        cursor.execute(query)
        myresult = cursor.fetchall()

        dbList = []
        for val in myresult:
            dbList.append(fileset('db', val[0], val[1],val[2], val[3],val[4]))

        cursor.close() 
        return dbList

    cnx.close()

def sortApiResponseById(e):
  return e['abbr']

def getApiFilesets(mykey):
    access_group_id = int(mykey[-3:])
    url = apiUrl + 'bibles?key='+ mykey + '&v=4' 
    if mykey.__contains__('5'):
        url = url + '&asset_id=dbp-vid'
    print(url)
    try:
        response = urlopen(url)
    except IOError:
        print("caught IOError exception on url:" + url)
        raise 

    apiResult = json.load(response)
    apiList = apiResult['data']
    apiList.sort(key=sortApiResponseById)

    newApi = []
    for el in apiList:
        try:
            if el['filesets']['dbp-vid']:
                for fs in el['filesets']['dbp-vid']:
                    newApi.append(fileset('api', access_group_id, fs['id'], 'dbp-vid', fs['type'], fs['size']))
        except KeyError:
            pass  

        try:
            if el['filesets']['dbp-prod']:
                for fs in el['filesets']['dbp-prod']:
                    newApi.append(fileset('api', access_group_id, fs['id'], 'dbp-prod', fs['type'], fs['size']))
        except KeyError:
            pass

    return newApi


def compareFilesets(mykey):
    try:
        os.makedirs('results')
    except OSError as e:
        if e.errno != errno.EEXIST:
            raise
    
    dbList = getDatabaseFilesets(mykey)
    dbFilesets = len(dbList)
    
    apiList = getApiFilesets(mykey)
    apiNum = len(apiList)

    diff = Diff(dbList, apiList)

    print(mykey)
    print('DB count: ' + str(dbFilesets))
    print('API count: ' + str(apiNum))
    print('diff count: ' + str(len(diff)))

    diffFilename = 'results/diff-'+mykey+'.json'
    with open(diffFilename, 'w') as outfile:
      outfile.write('[\n')
      outfile.write(",\n".join([ fileset.toJson() for fileset in diff]))
      outfile.write('\n]')

    print ('diff results can be found in '+ diffFilename)  

if len(sys.argv) > 1:
    print('running compareFileset for: ' + sys.argv[1])
    compareFilesets(sys.argv[1])

else:

    with open('testkeys.csv', newline='') as csvfile:
        keyreader = csv.reader(csvfile, delimiter=' ', quotechar='|')
        for row in keyreader:
            compareFilesets(row[0])



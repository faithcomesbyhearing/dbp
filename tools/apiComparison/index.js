//import libraryBookV2 from './stock-json/library-book/dbp2-response.json'
import _ from 'lodash';
import fs from 'fs';

let dataSet = 'library-volumelanguage'

let dbp2 = JSON.parse(fs.readFileSync('./stock-json/'+dataSet+'/dbp2-response.json'));
let dbp4 = JSON.parse(fs.readFileSync('./stock-json/'+dataSet+'/dbp4-response.json'));


function allV2PropertiesPresent(resV2, resV4) {

    function compareByList(listV2, listV4){
        let diff =  _.difference(_.keys(listV2[0]), _.keys(listV4[0]) ).length === 0;
        if (!diff){return false}
        //console.log('passes test: ', diff);
    }

    //compare top level
    compareByList(resV2, resV4)


    _.forEach((resV2[0]), function(obj, name) { 
        if(_.isObject(obj)){// tests for objects and arrays
            compareByList(resV2[0][name], resV4[0][name])
        }
    })
    return true



    
}


allV2PropertiesPresent(dbp2, dbp4)

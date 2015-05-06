# Table of Functions and Their Status #

The Tables below list the methods on all of the services and whether or not they are implemented via the API calls and their return values.

This CMIS client leverages the REST Binding and turns the ATOM-PUB structures that are returned into PHP Data Structures that organize the information in a way that matches the Domain Model of the CMIS Spec.

## Repository Services ##
| **Function** | **Status** | **Return Type** |
|:-------------|:-----------|:----------------|
| Get Repositories | _N/A_ | _N/A_ |
| Get Repository Info | Yes | Repository Definition |
| Get Type Children | Yes | List of Types |
| Get Type Descendants | Yes | Tree of Types |
| Get Type Definition | Yes | Type Definition |

## Navigation Services ##

| **Function** | **Status** | **Return Type** |
|:-------------|:-----------|:----------------|
| Get Folder Tree | Yes | Tree of Folders |
| Get Descendants | Yes | Tree of Folders and Documents |
| Get Children | Yes | List of Objects |
| Get Folder Parent | Yes | Folder Object |
| Get Object Parents | Yes | List Folder Objects |
| Get Checkedout Docs | Yes | List of Document Objects |

## Discovery Services ##

| **Function** | **Status** | **Return Type** |
|:-------------|:-----------|:----------------|
| Query | Yes | List Folder Objects |
| Get Content Changes | No | ??? |

## Object Services ##

| **Function** | **Status** | **Return Type** |
|:-------------|:-----------|:----------------|
| Get Object | Yes | CMIS Object |
| Get Object By Path | Yes | CMIS Object |
| Get Properties | Yes | CMIS Object |
| Get Allowable Actions | No | ??? |
| Get Renditions | Yes | CMIS Object |
| Get Content Stream | Yes | Content Stream |
| Create Document | Yes | Object ID (CMIS Object Returned) |
| Create Document From Source | _N/A_ | _N/A_ |
| Create Folder | Yes | Object ID (CMIS Object Returned) |
| Create Relationship | No | Object ID (CMIS Object Returned) |
| Create Policy | No | Object ID (CMIS Object Returned) |
| Update Properties | Yes | Object ID+Change Token (CMIS Object Returned) |
| Move Object | Yes | Object Id (CMIS Object Returned) |
| Delete Object | Yes | None |
| Delete Tree | No | List of Object IDs (Objects that could not be deleted) (CMIS Objects Returned?) |
| Set Content Stream | Yes | Object ID+Change Token (CMIS Object Returned) |
| Delete Content Stream | Yes | Object ID+Change Token (CMIS Object Returned) |

## Versioning Services ##

| **Function** | **Status** | **Return Type** |
|:-------------|:-----------|:----------------|
| Check Out | No | ??? |
| Check In | No | ??? |
| Cancel Check Out | No | ??? |
| Get Properties Of Latest Version | _Incomplete Do Not Use_ | ??? |
| Get Object Of Latest Version  | _Incomplete Do Not Use_ | ??? |
| Get All Versions | No | ??? |
| Delete All Versions | No | ??? |

## Relationship Services ##

| **Function** | **Status** | **Return Type** |
|:-------------|:-----------|:----------------|
| Get Object Relationships | No | ??? |

## Multi-Filing Services ##

| **Function** | **Status** | **Return Type** |
|:-------------|:-----------|:----------------|
| Add Object To Folder | No | ??? |
| Remove Object From Folder | No | ??? |

## Policy Services ##

| **Function** | **Status** | **Return Type** |
|:-------------|:-----------|:----------------|
| Apply Policy | No | ??? |
| Remove Policy | No | ??? |
| Get Applied Policies | No | ??? |

## ACL Services ##

| **Function** | **Status** | **Return Type** |
|:-------------|:-----------|:----------------|
| Get ACL | No | ??? |
| Apply ACL | No | ??? |

# Documentation on Various Return Types #

| **Return Type** | **Atom Pub Type** | **Description of PHP Structure** | **Comments** |
|:----------------|:------------------|:---------------------------------|:-------------|
| Repository Definition | Workspace |An object with 5 arrays<ol><li>Links (used by the client to navigate the repository)<li>URI Templates (used by the client to navigate the repository<li>Collections (used by the client to navigate the repository)<li>Capabilities<li>Repository Information <table><thead><th>  </th></thead><tbody>
<tr><td> CMIS Object </td><td> Entry </td><td> An Object with 2 arrays and 2 scalars<ol><li>Links  (used by the client to navigate the repository)<li>Properties<li>UUID<li>ID (Object ID) </td><td> CMIS Object can refer to:<ul><li>Document<li>Folder<li>Policy<li>Relationship<li>Object ID<li>Object ID+Change Token </td></tr>
<tr><td> List of CMIS Objects </td><td> Feed </td><td> PHP object with 2 Arrays of Entry Objects:<ul><li><b>objectsById</b> - an associative array of the Entries<li><b>objectList</b> - an array of references to the objets in the <b>objectsById</b> array </td><td> Objects in the feed may not be fully populated </td></tr>
<tr><td> Tree of CMIS Objects </td><td> Feed with CMIS Hierarchy Extensions </td><td> Array similar to above. Hierarchy is achieved by adding a "children" object to each Entry that has children. The "Children" object contains the same structure as the Feed (2 arrays) </td><td> Objects in the feed may not be fully populated </td></tr>
<tr><td> Type Definition </td><td> Entry </td><td> An Object with 3 arrays and 1 scalar<ol><li>Links  (used by the client to navigate the repository)<li>Properties<li>Attributes<li>ID (Object Type ID) </td><td> The Type Definition data structure needs work for completion. Currently it has enough to support the needs of the Object Services  </td></tr>
<tr><td> List of Type Definitions </td><td> Feed </td><td> PHP object with 2 Arrays of Entry Objects:<ul><li><b>objectsById</b> - an associative array of the Entries<li><b>objectList</b> - an array of references to the objets in the <b>objectsById</b> array </td><td> Objects in the feed may not be fully populated </td></tr>
<tr><td> Tree of Type Definitions </td><td> Feed with CMIS Hierarchy Extensions </td><td> Array similar to above. Hierarchy is achieved by adding a "children" object to each Entry that has children. The "Children" object contains the same structure as the Feed (2 arrays) </td><td> Objects in the feed may not be fully populated </td></tr>
<tr><td> Content Stream </td><td> Content </td><td> Content </td><td>   </td></tr>
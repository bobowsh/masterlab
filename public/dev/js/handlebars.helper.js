$(function () {

    if ("undefined" != typeof Handlebars.registerHelper) {
        Handlebars.registerHelper('if_eq', function (v1, v2, opts) {
            if (v1 == v2)
                return opts.fn(this);
            else
                return opts.inverse(this);
        });
    }

    Handlebars.registerHelper('if_in_array', function (value, arr, opts) {
        //console.log(value,arr );
        var ret = false;
        if(arr){
            for (i = 0; i < arr.length; i++) {
                if (value == arr[i]) {
                    ret = true;
                    break;
                }
            }
        }

        if (ret) {
            return opts.fn(this);
        } else {
            return opts.inverse(this);
        }
    });

    Handlebars.registerHelper("compare", function (x1, x2, options) {

        if (x1 > x2) {
            return options.fn(this);
        } else {
            return options.inverse(this);
        }
    });
    Handlebars.registerHelper("lessThan", function (var1, var2, options) {

        if (var1 < var2) {
            return options.fn(this);
        } else {
            return options.inverse(this);
        }
    });
    Handlebars.registerHelper("greaterThan", function (var1, var2, options) {

        if (var1 > var2) {
            return options.fn(this);
        } else {
            return options.inverse(this);
        }
    });

    Handlebars.registerHelper("between", function (value, min, max, options) {

        if (value > min && min < max) {
            return options.fn(this);
        } else {
            return options.inverse(this);
        }
    });

    Handlebars.registerHelper('lightSearch', function (summary, search) {

        var html = '';
        if (search == '') {
            return summary;
        }
        var fen = summary.split(search);
        html = fen.join('<span style="background:#cfc;">' + search + '</span> ');
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('make_types', function (type_ids, issue_types) {

        var html = '';
        if (type_ids == null || type_ids == undefined || type_ids == '') {
            return html;
        }
        var scheme_ids_arr = type_ids.split(',');
        scheme_ids_arr.forEach(function (type_id) {
            console.log(type_id);
            var type_name = '';
            var type_font_icon = '';
            for (var skey in issue_types) {
                if (issue_types[skey].id == type_id) {
                    type_name = issue_types[skey].name;
                    type_font_icon = issue_types[skey].font_awesome;
                    break;
                }
            }
            html += "<div class=\"branch-commit\"><i class='fa " + type_font_icon + "'></i> <a class=\"commit-id monospace\" href=\"#\">" + type_name + "</a></div>";
        });
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('make_projects', function (project_ids, projects) {
        var html = '';
        if (project_ids == null || project_ids == undefined || project_ids == '') {
            return html;
        }
        var project_ids_arr = project_ids.split(',');
        project_ids_arr.forEach(function (project_id) {
            console.log(project_id);
            var project_name = '';
            for (var skey in projects) {
                if (projects[skey].id == project_id) {
                    project_name = projects[skey].name;
                    break;
                }
            }
            html += "<div class=\"branch-commit\"> <a class=\"commit-id monospace\" href=\"#\">" + project_name + "</a></div>";
        });
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('make_project', function (project_id) {
        var html = '';
        if (project_id == null || project_id == undefined || project_id == '') {
            return html;
        }
        console.log(project_id);
        if (_issueConfig.projects == null || _issueConfig.projects == undefined) {
            return html;
        }
        var projects = _issueConfig.projects;
        var project_name = '';
        var org_path = 'default';
        var project_key = '';
        for (var skey = 0; skey < projects.length; skey++) {
            if (projects[skey].id == project_id) {
                project_name = projects[skey].name;
                org_path = projects[skey].org_path;
                project_key = projects[skey].key;
                break;
            }
        }
        var project_url = root_url + org_path + "/" + project_key;
        html += "<div class=\"branch-commit\"> <a class=\"commit-id monospace\" href='" + project_url + "' >" + project_name + "</a></div>";

        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('make_user', function (uid, users) {
        var html = '';
        if (uid == null || uid == undefined || uid == '') {
            return '';
        }
        var user = getValueByKey(users, uid);
        //console.log(users);
        if (user == null) {
            return '';
        }
        html += '<span class="list-item-name"><a   title="'+user.display_name+'"  href="/' + user.username + '"><image width="26px" height="26px" class="header-user-avatar has-tooltip float-none" data-placement="left" data-original-title="' + user.username + ' @' + user.display_name + '" src="' + user.avatar + '" /></a></span>';
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('user_html', function (uid) {
        var html = '';
        if (uid == null || uid == undefined || uid == '') {
            return '';
        }
        var user = getArrayValue(_issueConfig.users, 'uid', uid);
        if (user == null) {
            return '';
        }
        html += '<span class="list-item-name"><a   title="'+user.display_name+'"  href="/user/profile/' + user.uid + '">' +
            '<img width="26px" height="26px" class=" float-none" style="border-radius: 50%;"   data-toggle="tooltip" data-placement="bottom"  title="' + user.username + ' ' + user.display_name + '" src="' + user.avatar + '" />' +
            '</a></span>';
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('user_html_display_name', function (uid) {
        var html = '';
        if (uid == null || typeof(uid) == "undefined" || uid == '') {
            return '';
        }
        var user = getArrayValue(_issueConfig.users, 'uid', uid);
        if (user == null) {
            return '';
        }
        if (user == null || typeof(user) == "undefined" || !user ) {
            return '';
        }
        html += '<span class="list-item-name"><a   title="'+user.display_name+'"  href="/user/profile/' + user.uid + '">' +
            user.display_name +
            '</a></span>';
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('user_text_display_name', function (uid) {
        var html = '';
        if (uid == null || typeof(uid) == "undefined" || uid == '') {
            return '';
        }
        var user = getArrayValue(_issueConfig.users, 'uid', uid);
        if (user == null) {
            return '';
        }
        if (user == null || typeof(user) == "undefined" || !user ) {
            return '';
        }
        html += '<span class="list-item-name">' +
            user.display_name +
            '</span>';
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('org_user_html', function (uid) {
        var html = '';
        if (uid == null || uid == undefined || uid == '') {
            return '';
        }
        var user = getArrayValue(_issueConfig.users, 'uid', uid);
        //console.log(users);
        if (user == null) {
            return '';
        }
        html += ' <a href="/user/profile/' + user.uid + '">' +
            '<img width="26px" height="26px" class=" float-none" style="border-radius: 50%;"  ' +
            ' data-toggle="tooltip" data-placement="top"  title="负责人:' + user.username + ' @' + user.display_name + '" src="' + user.avatar + '" />' +
            ' ' + user.display_name +
            '</a>';
        return new Handlebars.SafeString(html);
    });
    //
    Handlebars.registerHelper('issue_assistants_avatar', function (uid_arr) {
       // console.log('issue_assistants_avatar:');
       // console.log(uid_arr);
        var users = _issueConfig.users;
        //console.log(users);
        var html = '';
        for (i = 0; i < uid_arr.length; i++) {
            let uid = _.toString(uid_arr[i]);
            let user = getArrayValue(_issueConfig.users, 'uid', uid);
            if (!_.has(user, 'uid')) {
                return '';
            }
            html += '<span class="list-item-name"><a title="'+user.display_name+'" href="/user/profile/' + user.uid + '"><img width="26px" height="26px" class="has-tooltip float-none" style="border-radius: 50%;" data-placement="left" data-original-title="' + user.username + ' @' + user.display_name + '" src="' + user.avatar + '" /></a></span>';
        }
        //console.log(html);
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('issue_assistants_display_name', function (uid_arr) {
        // console.log('issue_assistants_display_name:');
        // console.log(uid_arr);
        var users = _issueConfig.users;
        //console.log(users);
        var html = '';
        for (i = 0; i < uid_arr.length; i++) {
            let uid = _.toString(uid_arr[i]);
            let user = getArrayValue(_issueConfig.users, 'uid', uid);
            if (!_.has(user, 'uid')) {
                return '';
            }
            html += '<span class="list-item-name" style="margin-right: 4px"><a title="'+user.display_name+'" href="/user/profile/' + user.uid + '">' + user.display_name + '</a></span>';
        }
        //console.log(html);
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('user_account_str', function (uid) {
        var html = '';
        if (uid == null || uid == undefined || uid == '') {
            return '';
        }
        var user = getArrayValue(_issueConfig.users, 'uid', uid);
        //console.log(users);
        if (user == null) {
            return '';
        }
        html += '<span class="list-item-name"><a   title="'+user.display_name+'"  href="/user/profile/' + user.uid + '">' + user.username + ' ' + user.display_name + '</a></span>';
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('make_assistants', function (uid_arr) {
        //console.log(uid_arr);
        //console.log(_issueConfig.users);
        var html = '';
        for (i = 0; i < uid_arr.length; i++) {
            let uid = _.toString(uid_arr[i]);
            let user = getArrayValue(_issueConfig.users, 'uid', uid);
            //console.log(user);
            if (user == null) {
                return '';
            }
            html += '<div class="participants-author js-participants-author">';
            html += '    <a class="author_link has-tooltip"  title="'+user.display_name+'" data-container="body" href="' + root_url + 'user/profile/' + uid + '" data-original-title="' + user.display_name + '" ><img width="24" class="avatar avatar-inline s24 " alt="" src="' + user.avatar + '"></a>';
            html += '    </div>';
        }
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('assistants_html', function (uid_arr) {
        //console.log(uid_arr);
        var html = '';
        for (i = 0; i < uid_arr.length; i++) {
            let uid = parseInt(uid_arr[i]);
            let user = getArrayValue(_issueConfig.users, 'uid', uid);
            console.log(user);
            if (user == null) {
                return '';
            }
            html += '<div class="participants-author js-participants-author">';
            html += '    <a class="author_link has-tooltip"  title="'+user.display_name+'" data-container="body" href="/' + user.username + '" data-original-title="' + user.display_name + '" ><img width="24" class="avatar avatar-inline s24 " alt="" src="' + user.avatar + '"></a>';
            html += '    </div>';
        }
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('make_label_html', function (label_id_arr) {
        //console.log(label_id_arr);
        var html = '';
        if(label_id_arr){
            for (i = 0; i < label_id_arr.length; i++) {
                //var id = parseInt(label_id_arr[i]);
                var id = label_id_arr[i];
                var row = getArrayValue(_issueConfig.issue_labels, 'id',id);
                if (row == null) {
                    return '';
                }
                html += '<a href="'+cur_path_key +'?sys_filter=label_'+id+'">';
                html += '<span class="label color-label " style="background-color:'+row.bg_color+'; color: '+row.color+'" title="'+row.title+'" data-container="body">'+row.title+'</span>';
                html += '</a>';
            }
        }

        return new Handlebars.SafeString(html);
    });



    Handlebars.registerHelper('version_html', function (version_id) {
        var html = '';
        if (is_empty(version_id)) {
            return '';
        }
        var version_row = getArrayValue(_issueConfig.version, 'id', version_id);
        if (version_row == null) {
            return '';
        }
        html += '<span >' + version_row.name + '</span>';
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('priority_html', function (priority_id) {
        var html = '';
        if (is_empty(priority_id)) {
            return '';
        }
        var priority_row = getArrayValue(_issueConfig.priority, 'id', priority_id);
        if (priority_row == null) {
            return '';
        }
        html += '<span style="color:' + priority_row.status_color + '">' + priority_row.name + '</span>';
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('status_html', function (status_id) {
        var html = '';
        if (is_empty(status_id)) {
            return '';
        }
        var status_row = getArrayValue(_issueConfig.issue_status, 'id', status_id);
        if (status_row == null) {
            return '';
        }
        html += '<span class="label current label-' + status_row.color + ' prepend-left-5" style="cursor:pointer;">' + status_row.name + '</span>';
        return new Handlebars.SafeString(html);
    });


    Handlebars.registerHelper('resolve_html', function (resolve_id) {
        var html = '';
        if (is_empty(resolve_id)) {
            return '';
        }
        var resolve = getArrayValue(_issueConfig.issue_resolve, 'id',resolve_id);
        if (resolve == null) {
            html = '<span>一</span>';
            return new Handlebars.SafeString(html);
        }
        html += '<span style="color:' + resolve.color + '">' + resolve.name + '</span>';
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('module_html', function (module_id) {

        var html = '';
        if (is_empty(module_id)) {
            return '';
        }

        var module = getArrayValue(_issueConfig.issue_module, 'id', module_id);
        if (module == null) {
            html += '<a href="javascript:;" class="commit-id monospace"></a>';
        } else {
            html += '<a href="?state=opened&模块=' + module.name + '" class="commit-id monospace">' + module.name + '</a>';
        }        

        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('make_issue_sprint', function (sprint_id) {

        var html = '';
        if (is_empty(sprint_id)) {
            return '';
        }
        //console.log(_issueConfig.sprint);
        var sprint = getArrayValue(_issueConfig.sprint, 'id', sprint_id);
        //console.log(sprint);
        if (sprint == null) {
            html += '<a href="javascript:;" class="commit-id monospace"></a>';
        } else {
            html += '<a href="?state=opened&迭代=' + sprint.name + '" class="commit-id monospace">' + sprint.name + '</a>';
        }
        
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('make_issue_type', function (issue_type_id, issue_types) {
        var html = '';
        if (is_empty(issue_type_id)) {
            return '';
        }
        var issue_type = getArrayValue(issue_types, 'id',issue_type_id);
        if (issue_type == null) {
            return '';
        }
        html += '<i class="fa ' + issue_type.font_awesome + '"></i>\n' +
            '            <a href="#"  class="commit-id monospace">' + issue_type.name + '</a>';
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('issue_type_html', function (issue_type_id) {
        var html = '';
        if (is_empty(issue_type_id)) {
            return '';
        }
        var issue_type = getArrayValue(_issueConfig.issue_types, 'id', issue_type_id);
        if (issue_type == null) {
            return '';
        }
        html += '<i class="fa ' + issue_type.font_awesome + '"></i>\n' +
            '            <a href="#"  class="commit-id monospace">' + issue_type.name + '</a>';
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('issue_type_short_html', function (issue_type_id) {
        var html = '';
        if (is_empty(issue_type_id)) {
            return '';
        }
        var issue_type = getArrayValue(_issueConfig.issue_types, 'id',issue_type_id);
        if (issue_type == null) {
            return '';
        }
        html += '<i class="fa ' + issue_type.font_awesome + '" title="' + issue_type.name + '"></i>\n' +
            '';
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('issue_type_icon', function (issue_type_id) {
        var html = '';
        if (is_empty(issue_type_id)) {
            return '';
        }
        var issue_type = getArrayValue(_issueConfig.issue_types, 'id', issue_type_id);
        if (issue_type == null) {
            return '';
        }
        html += '<i class="fa ' + issue_type.font_awesome + '"></i>';
        return new Handlebars.SafeString(html);
    });

    Handlebars.registerHelper('make_backlog_issue_type', function (issue_type_id) {
        var html = '';
        if (is_empty(issue_type_id)) {
            return '';
        }
        var issue_type = getArrayValue(_issueConfig.issue_types, 'id',issue_type_id);
        if (issue_type == null) {
            return '';
        }
        html += '<i class="fa ' + issue_type.font_awesome + '"></i>\n'
        return new Handlebars.SafeString(html);
    });
    Handlebars.registerHelper('user_name_html', function (uid) {
        var html = '';
        if (is_empty(uid)) {
            return '';
        }
        var user = getArrayValue(_issueConfig.users, 'uid', uid);
        //console.log(users);
        if (user == null) {
            return '';
        }
        html += '<span class="list-item-name">by ' + user.display_name + '</span>';
        return new Handlebars.SafeString(html);
    });
    Handlebars.registerHelper('updated_text_html', function (updated_text) {
        var html = '';
        if (is_empty(updated_text)) {
            return '';
        }
        html += '<span   style="color:#707070">更新于 ' + updated_text + '</span>';
        return new Handlebars.SafeString(html);
    });


    Handlebars.registerHelper('created_text_html', function (created, created_text, created_full) {
        var html = '';
        if (is_empty(created_text)) {
            return '';
        }
        html += '·创建于<span  datetime="' + created + '" data-toggle="tooltip" data-placement="top" title="' + created_full + '">' + created_text + '</span>';

        return new Handlebars.SafeString(html);
    });

});
<template>
  <section class="page">
    <el-card shadow="never" class="member-card">
      <template #header>
        <div class="card-header">
          <strong>会员信息</strong>
          <el-button type="success" :icon="Plus" @click="openCreate">创建</el-button>
        </div>
      </template>

      <el-form class="search-form" label-position="top">
        <el-form-item label="ID">
          <el-input v-model.trim="search.account_id" placeholder="会员编号" clearable />
        </el-form-item>
        <el-form-item label="手机号">
          <el-input v-model.trim="search.phone" placeholder="手机号 / 账号" clearable />
        </el-form-item>
        <el-form-item label="上级编号">
          <el-input v-model.trim="search.parent_id" placeholder="上级编号" clearable />
        </el-form-item>
        <el-form-item label="邀请码">
          <el-input v-model.trim="search.invite_code" placeholder="邀请码查询" clearable />
        </el-form-item>
      </el-form>

      <div class="search-actions">
        <el-button :icon="Refresh" @click="resetSearch">刷新</el-button>
        <el-button type="primary" :icon="Search" @click="handleSearch">查找</el-button>
      </div>

      <p class="table-tip">第{{ startIndex }}-{{ endIndex }}条，共{{ total }}条数据。</p>

      <el-table :data="users" v-loading="loading" class="member-table" row-key="id">
        <el-table-column prop="id" label="ID" width="78" />

        <el-table-column label="账号" min-width="160">
          <template #default="{ row }">
            <div class="multi-line">
              <span>ID:{{ row.id }}</span>
              <span>在线:
                <el-tag size="small" :type="row.is_online ? 'success' : 'info'">
                  {{ row.is_online ? '在线' : '离线' }}
                </el-tag>
              </span>
              <span>账号: {{ row.phone }}</span>
              <span>国家: +1</span>
              <span>邀请码: {{ row.my_invite_code || '-' }}</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="推荐人" min-width="160">
          <template #default="{ row }">
            <div class="multi-line">
              <span>ID:{{ row.parent_id || '-' }}</span>
              <span>账号: {{ row.parent_phone || '-' }}</span>
              <span>邀请码: {{ row.invite_code || '-' }}</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="当前级别" min-width="100">
          <template #default="{ row }">
            <el-tag type="primary">{{ row.member_level || 'New Member Activities' }}</el-tag>
          </template>
        </el-table-column>

        <el-table-column label="是否虚拟会员" width="110">
          <template #default="{ row }">
            <el-tag :type="Number(row.is_fake_account) === 1 ? 'danger' : 'primary'">
              {{ Number(row.is_fake_account) === 1 ? '是' : '否' }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="账户金额" min-width="150">
          <template #default="{ row }">
            <div class="multi-line">
              <span>账户余额: {{ formatPlain(row.balance) }}</span>
              <span>冻结金额: {{ formatPlain(row.frozen_balance) }}</span>
              <span>累计充值: {{ formatPlain(row.total_recharge) }}</span>
              <span>累计提现: {{ formatPlain(row.total_withdrawn) }}</span>
              <span>任务奖金: {{ formatPlain(row.task_commission ?? row.total_commission) }}</span>
              <span>团队奖金: {{ formatPlain(row.team_commission) }}</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="每日订单数" width="100">
          <template #default="{ row }">
            <div class="multi-line">
              <span>{{ row.today_completed || 0 }}/{{ row.today_total || 0 }}</span>
              <span>未完成</span>
              <span>(未设置)</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="信誉分" width="90">
          <template #default="{ row }">{{ row.credit_score || 100 }}</template>
        </el-table-column>

        <el-table-column label="最后登录" min-width="190">
          <template #default="{ row }">
            <div class="multi-line">
              <span>最后访问IP: {{ row.last_login_ip || '-' }}</span>
              <span>最后访问: {{ row.last_login_at || row.updated_at || '-' }}</span>
              <span>登录次数: {{ row.login_count || 0 }}</span>
              <span>注册时间: {{ row.created_at || '-' }}</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="记录操作" width="150">
          <template #default="{ row }">
            <div class="button-row">
              <el-button size="small" @click="openTasks(row)">任务记录</el-button>
              <el-button size="small" @click="openRecords(row)">账户明细</el-button>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="订单操作" width="200">
          <template #default="{ row }">
            <div class="button-col">
              <el-button size="small" @click="openTask(row)">修改单数</el-button>
              <!-- <el-button size="small" @click="openCountReset(row)">重置单数</el-button> -->
              <el-button size="small" @click="openReset(row)">连单设置</el-button>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="操作" width="250" fixed="right">
          <template #default="{ row }">
            <div class="action-grid">
              <el-button size="small" type="primary" link @click="openPassword(row)">账号密码</el-button>
              <el-button size="small" type="success" link @click="openLevel(row)">修改等级</el-button>
              <el-button size="small" type="success" link @click="openCredit(row)">修改信誉分</el-button>
              <el-button size="small" type="danger" link @click="openBalance(row, 'deposit')">加扣款</el-button>
              <el-button size="small" type="primary" link @click="openEditor(row)">编辑</el-button>
              <!-- <el-switch v-model="row.status" :active-value="1" :inactive-value="0" @change="toggleStatus(row)" /> -->
            </div>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :total="total"
          layout="total, sizes, prev, pager, next"
          :page-sizes="[10, 20, 50]"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </el-card>

    <el-dialog v-model="createDialog.visible" title="创建会员" width="520px">
      <el-form label-width="110px">
        <el-form-item label="账号">
          <el-input v-model.trim="createDialog.form.phone" placeholder="" />
        </el-form-item>
        <el-form-item label="登录密码">
          <el-input v-model.trim="createDialog.form.password" type="password" show-password />
        </el-form-item>
        <el-form-item label="提现密码">
          <el-input v-model.trim="createDialog.form.withdrawal_password" type="password" show-password />
        </el-form-item>
        <el-form-item label="邀请码">
          <el-input v-model.trim="createDialog.form.invite_code" placeholder="可填客户或业务员邀请码" />
        </el-form-item>
        <el-form-item label="账号角色">
          <el-select v-model="createDialog.form.account_role" style="width: 100%">
            <el-option label="业务员" value="agent" />
            <el-option label="客户" value="customer" />
            <el-option label="虚拟会员" value="managed" />
          </el-select>
        </el-form-item>
        <el-form-item label="初始余额">
          <el-input-number v-model="createDialog.form.balance" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="已完成订单数">
          <el-input-number v-model="createDialog.form.completed_tasks" :min="0" :precision="0" style="width: 100%" />
          <div class="form-help">创建后会自动生成今日订单，可直接配置包裹任务</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialog.visible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitCreate">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="passwordDialog.visible" title="账号密码" width="460px">
      <el-form label-width="110px">
        <el-form-item label="会员账号">{{ passwordDialog.user?.phone }}</el-form-item>
        <el-form-item label="登录密码">
          <el-input v-model.trim="passwordDialog.password" type="password" show-password placeholder="留空则不修改" />
        </el-form-item>
        <el-form-item label="提现密码">
          <el-input v-model.trim="passwordDialog.withdrawal_password" type="password" show-password placeholder="留空则不修改" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="passwordDialog.visible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitPassword">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="balanceDialog.visible" title="加扣款" width="460px">
      <el-form label-width="110px">
        <el-form-item label="会员账号">{{ balanceDialog.user?.phone }}</el-form-item>
        <el-form-item label="当前余额">{{ formatMoney(balanceDialog.user?.balance) }}</el-form-item>
        <el-form-item label="操作类型">
          <el-select v-model="balanceDialog.type" style="width: 100%">
            <el-option label="加款" value="deposit" />
            <el-option label="扣款" value="adjust" />
          </el-select>
        </el-form-item>
        <el-form-item label="金额">
          <el-input-number v-model="balanceDialog.amount" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model.trim="balanceDialog.remark" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="balanceDialog.visible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitBalance">提交</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="taskDialog.visible" title="基本信息" width="720px">
      <el-form label-position="top" class="basic-form">
        <el-form-item label="已完成订单数">
          <el-input-number v-model="taskDialog.completed_tasks" :min="0" :max="taskDialog.total_tasks" :precision="0" style="width: 100%" />
          <div class="form-help">每日订单总数：{{ taskDialog.total_tasks }}</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="taskDialog.visible = false">关闭</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitTaskConfig">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="levelDialog.visible" title="修改等级" width="420px">
      <el-form label-width="100px">
        <el-form-item label="当前等级">
          <el-tag type="primary">New Member Activities</el-tag>
        </el-form-item>
        <el-form-item label="说明">
          <span class="muted">当前数据库暂无等级字段，此处先按参照页面展示固定等级。</span>
        </el-form-item>
      </el-form>
    </el-dialog>

    <el-dialog v-model="creditDialog.visible" title="基本信息" width="900px" class="credit-dialog">
      <el-form label-position="top" class="credit-form">
        <el-form-item label="信誉分">
          <el-input v-model.number="creditDialog.score" type="number" min="0" max="100" placeholder="100" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="creditDialog.visible = false">关闭</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitCreditScore">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="tasksDialog.visible" title="任务列表" width="980px">
      <p class="table-tip">第{{ taskStartIndex }}-{{ taskEndIndex }}条，共{{ tasksDialog.total }}条数据。</p>
      <el-table :data="tasksDialog.tasks" stripe max-height="430" class="task-record-table">
        <el-table-column prop="display_id" label="id" min-width="210" />
        <el-table-column prop="user_id" label="账号" width="80" />
        <el-table-column prop="quantity" label="数量" width="68" />
        <el-table-column label="单价" width="100">
          <template #default="{ row }">{{ formatPlain(row.unit_price ?? row.task_amount) }}</template>
        </el-table-column>
        <el-table-column label="佣金" width="90">
          <template #default="{ row }">{{ formatPlain(row.commission ?? row.profit) }}</template>
        </el-table-column>
        <el-table-column label="是否连单" width="100">
          <template #default="{ row }">{{ Number(row.is_combo ?? row.is_package) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <span>{{ taskStatus(row).label }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="ordered_at" label="下单时间" min-width="170" show-overflow-tooltip />
        <el-table-column prop="completed_time" label="完成交易时间" min-width="180" />
      </el-table>
      <div class="pagination">
        <el-pagination
          v-model:current-page="tasksDialog.page"
          v-model:page-size="tasksDialog.pageSize"
          :total="tasksDialog.total"
          layout="prev, pager, next, sizes"
          :page-sizes="[10, 20, 50]"
          @size-change="handleTaskSizeChange"
          @current-change="handleTaskCurrentChange"
        />
      </div>
    </el-dialog>

    <el-dialog v-model="recordsDialog.visible" title="余额日志" width="860px">
      <div class="dialog-toolbar">
        <el-button type="primary" :icon="Download" @click="exportRecords">导出</el-button>
        <el-button type="primary" :icon="Plus" @click="createRecordFromDialog">创建</el-button>
      </div>
      <p class="table-tip">第{{ recordStartIndex }}-{{ recordEndIndex }}条，共{{ recordsDialog.total }}条数据。</p>
      <el-table :data="recordsDialog.list" stripe max-height="520" class="balance-record-table">
        <el-table-column label="#" width="56">
          <template #default="{ $index }">{{ recordStartIndex + $index }}</template>
        </el-table-column>
        <el-table-column label="关联用户" min-width="160">
          <template #default="{ row }">
            <div class="multi-line record-user">
              <span>用户ID：{{ row.related_user?.id || row.user_id }}</span>
              <span>昵称：{{ row.related_user?.nickname || '' }}</span>
              <span>账号：{{ row.related_user?.account || '' }}</span>
              <span>手机：{{ row.related_user?.phone || '' }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="操作类型" width="100">
          <template #default="{ row }">{{ row.display_type || recordMeta(row.type).label }}</template>
        </el-table-column>
        <el-table-column label="变动数量" width="110">
          <template #default="{ row }">{{ formatPlain(row.display_amount ?? Math.abs(Number(row.change_amount))) }}</template>
        </el-table-column>
        <el-table-column label="备注" width="100">
          <template #default="{ row }">{{ row.remark || '(未设置)' }}</template>
        </el-table-column>
        <el-table-column label="变动记录" min-width="240">
          <template #default="{ row }">{{ row.change_record || `${formatPlain(row.before_balance)}${Number(row.change_amount) >= 0 ? '+' : ''}${formatPlain(row.change_amount)}=${formatPlain(row.after_balance)}` }}</template>
        </el-table-column>
      </el-table>
      <div class="pagination">
        <el-pagination
          v-model:current-page="recordsDialog.page"
          v-model:page-size="recordsDialog.pageSize"
          :total="recordsDialog.total"
          layout="prev, pager, next, sizes"
          :page-sizes="[10, 20, 50]"
          @size-change="handleRecordSizeChange"
          @current-change="handleRecordCurrentChange"
        />
      </div>
    </el-dialog>

    <el-dialog v-model="comboDialog.visible" title="连单设置" width="92%" class="combo-dialog" top="4vh">
      <div class="combo-toolbar">
        <!-- <el-button link :icon="Refresh" @click="reloadComboDialog">刷新</el-button> -->
        <el-button link @click="comboDialog.visible = false">返回</el-button>
      </div>

      <section class="combo-panel">
        <div class="combo-summary">
          <strong>连单设置</strong>
          <span>余额：{{ formatPlain(comboDialog.user?.balance) }}</span>
          <span>冻结：{{ formatPlain(comboDialog.user?.frozen_balance) }}</span>
          <span>当前已完成{{ comboDialog.user?.today_completed || 0 }}单</span>
        </div>

        <el-form label-width="130px" class="combo-form">
          <el-form-item label="第几单" required>
            <el-input v-model.number="comboDialog.task_index" />
          </el-form-item>
          <el-form-item label="">
            <el-button type="primary" @click="calculateComboBalance">计算余额</el-button>
          </el-form-item>
          <el-form-item label="每单佣金">
            <el-input v-model.number="comboDialog.commission_rate" />
            <div class="form-help">原佣金的倍数</div>
          </el-form-item>

          <div class="combo-search-row">
            <el-form-item label="任务金额">
              <el-input v-model.number="comboDialog.task_amount" placeholder="输入任务金额自动配置对应产品数量" />
            </el-form-item>
            <el-form-item label="允许最大差值" class="combo-diff">
              <el-input v-model.number="comboDialog.max_diff" />
            </el-form-item>
            <el-button type="primary" :loading="comboGoodsLoading" @click="searchComboGoods">搜索</el-button>
          </div>
        </el-form>

        <el-table
          :data="comboDialog.packages"
          class="combo-product-table"
          row-key="uid"
        >
          <el-table-column label="商品ID" min-width="120">
            <template #default="{ row, $index }">
              <span>{{ row.good_id }}</span>
              <el-button link type="danger" size="small" @click="removeComboPackage($index)">删除</el-button>
            </template>
          </el-table-column>
          <el-table-column prop="good_name" label="商品名" min-width="220" />
          <el-table-column label="单价" min-width="120">
            <template #default="{ row }">{{ formatPlain(row.price) }}</template>
          </el-table-column>
          <el-table-column prop="quantity" label="数量" min-width="120" />
          <el-table-column label="总价" min-width="120">
            <template #default="{ row }">{{ formatPlain(row.task_amount) }}</template>
          </el-table-column>
          <el-table-column label="佣金(%)" min-width="140">
            <template #default="{ row }">
              <el-input-number v-model="row.commission_rate" :min="0.01" :precision="2" :step="1" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="操作" min-width="120">
            <template #default="{ $index }">
              <el-button link type="primary" @click="replaceComboPackage($index)">换一个</el-button>
            </template>
          </el-table-column>
        </el-table>

        <div class="combo-actions">
          <el-button type="success" :loading="submitLoading" @click="submitCombo">保存</el-button>
          <el-button @click="comboDialog.visible = false">关闭</el-button>
        </div>
      </section>

      <h2 class="combo-current-title">当前连单</h2>
      <p class="table-tip">第1-{{ comboDialog.comboList.length }}条，共{{ comboDialog.comboList.length }}条数据。</p>
      <el-table :data="comboDialog.comboList" class="combo-list-table" row-key="item_id">
        <el-table-column prop="id" label="ID" min-width="110" />
        <el-table-column prop="account" label="会员账号" min-width="160" />
        <el-table-column prop="task_index" label="第几单" min-width="120" />
        <el-table-column label="产品价格" min-width="360">
          <template #default="{ row }">
            <div class="multi-line">
              <span>{{ row.product_text }}</span>
              <span>数量：{{ row.quantity || 1 }}</span>
              <span :class="Number(row.status) === 1 ? 'positive' : 'negative'">{{ row.status_label }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="本单佣金" min-width="140">
          <template #default="{ row }">{{ formatPlain(row.commission_rate) }}%</template>
        </el-table-column>
        <el-table-column label="Status" min-width="130">
          <template #default="{ row }">
            <span :class="Number(row.status) === 1 ? 'positive' : 'negative'">{{ row.status_label }}</span>
          </template>
        </el-table-column>
        <el-table-column min-width="110">
          <template #default="{ row }">
            <el-button type="danger" link @click="deleteCombo(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-dialog>
    <el-dialog v-model="rechargeDialog.visible" title="充值" width="900px" class="recharge-dialog">
      <div class="recharge-user">
        <div class="avatar-placeholder">
          <div class="avatar-head"></div>
          <div class="avatar-body"></div>
        </div>
        <div class="recharge-user-info">
          <div>ID: {{ rechargeDialog.user?.id || '-' }}</div>
          <div>昵称: {{ rechargeDialog.user?.nickname || '' }}</div>
          <div>姓名: {{ rechargeDialog.user?.real_name || '' }}</div>
          <div>手机号码: {{ stripUsCode(rechargeDialog.user?.phone) }}</div>
        </div>
      </div>

      <section class="recharge-panel">
        <h3>充值余额</h3>
        <el-form label-width="140px" class="recharge-form">
          <el-form-item label="当前">
            <el-input :model-value="formatPlain(rechargeDialog.user?.balance)" disabled />
          </el-form-item>
          <el-form-item label="变更">
            <el-radio-group v-model="rechargeDialog.change">
              <el-radio label="deposit">增加</el-radio>
              <el-radio label="adjust">减少</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="数量">
            <el-input v-model.number="rechargeDialog.amount" />
          </el-form-item>
          <div class="recharge-submit">
            <el-button type="success" :loading="submitLoading" @click="submitRecharge">提交</el-button>
          </div>
        </el-form>
      </section>
    </el-dialog>

    <el-dialog v-model="accountDialog.visible" title="编辑" width="51%" class="account-dialog" top="5vh">
      <!-- <div class="edit-toolbar">
        <el-button link :icon="Refresh" @click="reloadEditor">刷新</el-button>
        <el-button link @click="accountDialog.visible = false">返回</el-button>
      </div> -->

      <section class="edit-panel">
        <!-- <h3>编辑</h3> -->
        <el-form label-position="top" class="account-form">
          <el-form-item label="账号">
            <el-input v-model.trim="accountDialog.form.phone" />
          </el-form-item>
          <el-form-item label="昵称">
            <el-input v-model.trim="accountDialog.form.nickname" />
          </el-form-item>
          <el-form-item label="真实姓名">
            <el-input v-model.trim="accountDialog.form.real_name" />
          </el-form-item>
          <el-form-item label="邀请码">
            <el-input
              v-model.trim="accountDialog.form.my_invite_code"
              maxlength="6"
              placeholder="请输入4-6位数字"
              @input="accountDialog.form.my_invite_code = String(accountDialog.form.my_invite_code || '').replace(/\D/g, '').slice(0, 6)"
            />
          </el-form-item>
          <el-form-item label="状态">
            <el-radio-group v-model="accountDialog.form.status">
              <el-radio :label="1">启用</el-radio>
              <el-radio :label="0">禁用</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="图片上传">
            <el-upload
              class="account-uploader"
              action="#"
              :show-file-list="false"
              :http-request="uploadAccountImage"
              :before-upload="beforeAccountImageUpload"
            >
              <img v-if="accountDialog.form.avatar" :src="resolveImageUrl(accountDialog.form.avatar)" class="account-upload-preview" />
              <div v-else class="account-upload-placeholder">
                <el-icon  :size="40"><UploadFilled /></el-icon>
              </div>
            </el-upload>
          </el-form-item>
        </el-form>
      </section>

      <template #footer>
        <el-button type="primary" :loading="submitLoading" @click="submitAccountInfo">保存</el-button>
        <el-button @click="accountDialog.visible = false">返回</el-button>
      </template>
    </el-dialog>
    <el-dialog v-model="memberLevelDialog.visible" title="基本信息" width="900px" class="level-edit-dialog">
      <el-form label-position="top" class="level-edit-form">
        <el-form-item label="当前级别">
          <el-select v-model="memberLevelDialog.member_level" style="width: 100%">
            <el-option
              v-for="level in memberLevelOptions"
              :key="level.level"
              :label="`${level.level} - ${level.level_name}`"
              :value="String(level.level)"
            />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="memberLevelDialog.visible = false">关闭</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitMemberLevel">保存</el-button>
      </template>
    </el-dialog>
    <el-dialog v-model="accountPasswordDialog.visible" title="基本信息" width="960px" class="password-info-dialog">
      <el-form label-width="150px" class="password-info-form">
        <el-form-item label="用户名">
          <el-input :model-value="displayAccount(accountPasswordDialog.user)" disabled />
          <div class="form-help">创建后不可修改</div>
        </el-form-item>
        <el-form-item label="密码" class="highlight-label">
          <el-input
            v-model.trim="accountPasswordDialog.password"
            type="password"
            show-password
            placeholder="••••••••••••••••••••••••••••••••"
          />
        </el-form-item>
        <el-form-item label="提现密码">
          <el-input
            v-model.trim="accountPasswordDialog.withdrawal_password"
            type="password"
            show-password
            placeholder="••••••••••••••••••••••••••••••••"
          />
        </el-form-item>
        <el-form-item label="推荐人">
          <el-input :model-value="accountPasswordDialog.user?.parent_id || '-'" disabled />
        </el-form-item>
        <el-form-item label="真实姓名">
          <el-input v-model.trim="accountPasswordDialog.real_name" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="accountPasswordDialog.visible = false">关闭</el-button>
        <el-button type="success" :loading="submitLoading" @click="submitAccountPassword">保存</el-button>
      </template>
    </el-dialog>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Download, Plus, Refresh, Search, UploadFilled } from '@element-plus/icons-vue'
import { getGoods } from '@/api/good'
import { buildComboGoods } from '@/utils/comboGoods.mjs'
import { resolveAssetUrl } from '@/utils/url'
import {
  configUserTasks,
  createUser,
  deleteComboTask,
  getBalanceRecords,
  getComboTasks,
  getMemberLevels,
  getUsers,
  getUserTasks,
  resetUserTasks,
  saveComboTask,
  toggleUserStatus,
  updateUserBalance,
  updateUserCreditScore,
  updateUserLevel,
  updateUserInfo,
  updateUserPassword,
  uploadUserImage,
} from '@/api/user'

const loading = ref(false)
const submitLoading = ref(false)
const memberLevelOptions = ref([])
const users = ref([])
const page = ref(1)
const pageSize = ref(10)
const total = ref(0)
const search = reactive({ account_id: '', phone: '', parent_id: '', invite_code: '' })

const createDialog = reactive({
  visible: false,
  form: { phone: '', password: '', withdrawal_password: '', invite_code: '', account_role: 'customer', balance: 10, completed_tasks: 0, status: 1 },
})
const passwordDialog = reactive({ visible: false, user: null, password: '', withdrawal_password: '' })
const accountPasswordDialog = reactive({ visible: false, user: null, password: '', withdrawal_password: '', real_name: '' })
const accountDialog = reactive({
  visible: false,
  user: null,
  password: '',
  withdrawal_password: '',
  form: { phone: '', nickname: '', real_name: '', my_invite_code: '', status: 1, avatar: '' },
})
const balanceDialog = reactive({ visible: false, user: null, type: 'deposit', amount: 50, remark: '' })
const rechargeDialog = reactive({ visible: false, user: null, change: 'deposit', amount: '' })
const taskDialog = reactive({ visible: false, user: null, completed_tasks: 0, total_tasks: 0, task_amount: 100, task_date: '' })
const resetDialog = reactive({ visible: false, user: null, remark: '', task_date: '' })
const comboDialog = reactive({
  visible: false,
  user: null,
  task_index: '',
  commission_rate: 5,
  task_amount: '',
  max_diff: 10,
  goods: [],
  packages: [],
  comboList: [],
})
const levelDialog = reactive({ visible: false, user: null })
const memberLevelDialog = reactive({ visible: false, user: null, member_level: '1' })
const creditDialog = reactive({ visible: false, user: null, score: 100 })
const tasksDialog = reactive({ visible: false, user: null, tasks: [], page: 1, pageSize: 10, total: 0, task_date: '' })
const recordsDialog = reactive({ visible: false, user: null, list: [], page: 1, pageSize: 10, total: 0 })
const comboGoodsLoading = ref(false)

const startIndex = computed(() => (total.value ? (page.value - 1) * pageSize.value + 1 : 0))
const endIndex = computed(() => Math.min(page.value * pageSize.value, total.value))
const taskStartIndex = computed(() => (tasksDialog.total ? (tasksDialog.page - 1) * tasksDialog.pageSize + 1 : 0))
const taskEndIndex = computed(() => Math.min(tasksDialog.page * tasksDialog.pageSize, tasksDialog.total))
const recordStartIndex = computed(() => (recordsDialog.total ? (recordsDialog.page - 1) * recordsDialog.pageSize + 1 : 0))
const recordEndIndex = computed(() => Math.min(recordsDialog.page * recordsDialog.pageSize, recordsDialog.total))

const formatMoney = (value) => `$${Number(value || 0).toFixed(2)}`
const formatPlain = (value) => Number(value || 0).toFixed(2)
const displayAccount = (user) => user ? `user${String(user.phone || user.id).slice(-4)}` : ''
const stripUsCode = (phone) => String(phone || '').replace(/^\+1/, '')
const resolveImageUrl = (url) => {
  return resolveAssetUrl(url)
}

const handleSearch = () => {
  page.value = 1
  loadUsers()
}

const resetSearch = () => {
  search.account_id = ''
  search.phone = ''
  search.parent_id = ''
  search.invite_code = ''
  handleSearch()
}

const handleSizeChange = (val) => {
  pageSize.value = val
  handleSearch()
}

const handleCurrentChange = (val) => {
  page.value = val
  loadUsers()
}

const loadUsers = async () => {
  loading.value = true
  try {
    const res = await getUsers({ page: page.value, pageSize: pageSize.value, ...search })
    if (res.data.code === 200) {
      users.value = res.data.data.list || []
      total.value = res.data.data.total || 0
    } else {
      ElMessage.warning(res.data.msg || '加载会员失败')
    }
  } catch (error) {
    ElMessage.error('加载会员失败')
  } finally {
    loading.value = false
  }
}

const loadMemberLevels = async () => {
  try {
    const res = await getMemberLevels({ page: 1, pageSize: 100 })
    if (res.data.code === 200) {
      memberLevelOptions.value = res.data.data.list || []
    }
  } catch (error) {
    ElMessage.error('加载会员等级失败')
  }
}

const openCreate = () => {
  createDialog.form = { phone: '', password: '', withdrawal_password: '', invite_code: '', account_role: 'customer', balance: 10, completed_tasks: 0, status: 1 }
  createDialog.visible = true
}

const openPassword = (row) => Object.assign(accountPasswordDialog, {
  visible: true,
  user: row,
  password: '',
  withdrawal_password: '',
  real_name: row.real_name || '',
})
const openEditor = (row) => Object.assign(accountDialog, {
  visible: true,
  user: row,
  password: '',
  withdrawal_password: '',
  form: {
    phone: row.phone || '',
    nickname: row.nickname || '',
    real_name: row.real_name || '',
    my_invite_code: row.my_invite_code || '',
    status: Number(row.status ?? 1),
    avatar: row.avatar || row.image || '',
  },
})
const openBalance = (row, type = 'deposit') => {
  if (type === 'deposit') {
    Object.assign(rechargeDialog, { visible: true, user: row, change: 'deposit', amount: '' })
    return
  }
  Object.assign(balanceDialog, { visible: true, user: row, type, amount: 50, remark: '' })
}
const openTask = (row) => Object.assign(taskDialog, {
  visible: true,
  user: row,
  completed_tasks: Number(row.today_completed || 0),
  total_tasks: Number(row.today_total || 0),
  task_amount: Number(row.task_amount || 100),
  task_date: row.task_display_date || '',
})
const openCountReset = (row) => Object.assign(resetDialog, {
  visible: true,
  user: row,
  remark: '',
  task_date: row.task_display_date || '',
})
const openReset = async (row) => {
  Object.assign(comboDialog, {
    visible: true,
    user: row,
    task_index: '',
    commission_rate: 5,
    task_amount: '',
    max_diff: 10,
    goods: [],
    packages: [],
    comboList: [],
  })
  await loadComboList()
}
const openLevel = async (row) => {
  if (!memberLevelOptions.value.length) {
    await loadMemberLevels()
  }
  Object.assign(memberLevelDialog, {
    visible: true,
    user: row,
    member_level: String(row.member_level || memberLevelOptions.value[0]?.level || '1'),
  })
}
const openCredit = (row) => Object.assign(creditDialog, { visible: true, user: row, score: row.credit_score || 100 })

const beforeAccountImageUpload = (file) => {
  const isImage = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)
  const isLt5M = file.size / 1024 / 1024 < 5

  if (!isImage) ElMessage.warning('只能上传 JPG、PNG、GIF、WebP 图片')
  if (!isLt5M) ElMessage.warning('图片大小不能超过 5MB')

  return isImage && isLt5M
}

const uploadAccountImage = async ({ file, onSuccess, onError }) => {
  const formData = new FormData()
  formData.append('file', file)

  try {
    const res = await uploadUserImage(formData)
    if (res.data.code !== 200) throw new Error(res.data.msg || '上传失败')
    accountDialog.form.avatar = resolveImageUrl(res.data.data?.url)
    ElMessage.success('上传成功')
    onSuccess?.(res.data)
  } catch (error) {
    ElMessage.error(error.message || '上传失败')
    onError?.(error)
  }
}

const reloadEditor = () => {
  loadUsers()
  ElMessage.success('已刷新')
}

const submitCreditScore = async () => {
  if (!creditDialog.user) return
  const score = Number(creditDialog.score)
  if (!Number.isFinite(score) || score < 0 || score > 100) return ElMessage.warning('信誉分需为0-100之间的数字')
  submitLoading.value = true
  try {
    const res = await updateUserCreditScore({
      user_id: creditDialog.user.id,
      credit_score: score,
    })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('信誉分已保存')
    creditDialog.visible = false
    loadUsers()
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    submitLoading.value = false
  }
}

const submitCreate = async () => {
  if (!createDialog.form.phone) return ElMessage.warning('请输入账号')
  if (!createDialog.form.password || !createDialog.form.withdrawal_password) return ElMessage.warning('请填写密码')
  if (createDialog.form.completed_tasks === '' || createDialog.form.completed_tasks < 0) return ElMessage.warning('请输入已完成订单数')
  submitLoading.value = true
  try {
    const res = await createUser(createDialog.form)
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('创建成功')
    createDialog.visible = false
    loadUsers()
  } catch (error) {
    ElMessage.error(error.message || '创建失败')
  } finally {
    submitLoading.value = false
  }
}

const submitPassword = async () => {
  if (!passwordDialog.password && !passwordDialog.withdrawal_password) return ElMessage.warning('请输入要修改的密码')
  submitLoading.value = true
  try {
    const res = await updateUserPassword({
      user_id: passwordDialog.user.id,
      password: passwordDialog.password,
      withdrawal_password: passwordDialog.withdrawal_password,
    })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('密码已修改')
    passwordDialog.visible = false
  } catch (error) {
    ElMessage.error(error.message || '修改失败')
  } finally {
    submitLoading.value = false
  }
}

const submitAccountInfo = async () => {
  if (!accountDialog.user) return
  const form = accountDialog.form
  const unchanged = form.phone === (accountDialog.user.phone || '')
    && form.nickname === (accountDialog.user.nickname || '')
    && form.real_name === (accountDialog.user.real_name || '')
    && form.my_invite_code === (accountDialog.user.my_invite_code || '')
    && Number(form.status) === Number(accountDialog.user.status ?? 1)
    && (form.avatar || '') === (accountDialog.user.avatar || accountDialog.user.image || '')
  if (!accountDialog.password && !accountDialog.withdrawal_password && unchanged) {
    return ElMessage.warning('请输入要修改的信息')
  }
  if (!/^\d{4,6}$/.test(form.my_invite_code || '')) {
    return ElMessage.warning('邀请码请输入4-6位数字')
  }
  submitLoading.value = true
  try {
    const res = await updateUserInfo({
      user_id: accountDialog.user.id,
      password: accountDialog.password,
      withdrawal_password: accountDialog.withdrawal_password,
      phone: form.phone,
      nickname: form.nickname,
      real_name: form.real_name,
      my_invite_code: form.my_invite_code,
      status: form.status,
      avatar: form.avatar,
    })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('保存成功')
    accountDialog.visible = false
    loadUsers()
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    submitLoading.value = false
  }
}

const submitAccountPassword = async () => {
  if (!accountPasswordDialog.user) return
  const unchangedName = (accountPasswordDialog.real_name || '') === (accountPasswordDialog.user.real_name || '')
  if (!accountPasswordDialog.password && !accountPasswordDialog.withdrawal_password && unchangedName) {
    return ElMessage.warning('请输入要修改的信息')
  }
  submitLoading.value = true
  try {
    const res = await updateUserPassword({
      user_id: accountPasswordDialog.user.id,
      password: accountPasswordDialog.password,
      withdrawal_password: accountPasswordDialog.withdrawal_password,
      real_name: accountPasswordDialog.real_name,
    })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('保存成功')
    accountPasswordDialog.user.real_name = accountPasswordDialog.real_name
    accountPasswordDialog.visible = false
    await loadUsers()
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    submitLoading.value = false
  }
}

const submitMemberLevel = async () => {
  if (!memberLevelDialog.user) return
  submitLoading.value = true
  try {
    const res = await updateUserLevel({
      user_id: memberLevelDialog.user.id,
      member_level: memberLevelDialog.member_level,
    })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('保存成功')
    memberLevelDialog.user.member_level = memberLevelDialog.member_level
    memberLevelDialog.visible = false
    await loadUsers()
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    submitLoading.value = false
  }
}

const submitBalance = async () => {
  if (!balanceDialog.user) return
  if (!balanceDialog.amount) return ElMessage.warning('请输入金额')

  const amount = balanceDialog.type === 'adjust' && balanceDialog.amount > 0 ? -balanceDialog.amount : balanceDialog.amount
  submitLoading.value = true
  try {
    const res = await updateUserBalance({
      user_id: balanceDialog.user.id,
      operation_type: balanceDialog.type,
      amount,
      remark: balanceDialog.remark,
    })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('操作成功')
    balanceDialog.visible = false
    loadUsers()
  } catch (error) {
    ElMessage.error(error.message || '操作失败')
  } finally {
    submitLoading.value = false
  }
}

const submitRecharge = async () => {
  if (!rechargeDialog.user) return
  if (!rechargeDialog.amount || Number(rechargeDialog.amount) <= 0) return ElMessage.warning('请输入数量')

  const rawAmount = Number(rechargeDialog.amount)
  const amount = rechargeDialog.change === 'adjust' ? -rawAmount : rawAmount
  submitLoading.value = true
  try {
    const res = await updateUserBalance({
      user_id: rechargeDialog.user.id,
      operation_type: rechargeDialog.change,
      amount,
    })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('提交成功')
    rechargeDialog.visible = false
    loadUsers()
  } catch (error) {
    ElMessage.error(error.message || '提交失败')
  } finally {
    submitLoading.value = false
  }
}

const submitTaskConfig = async () => {
  if (!taskDialog.user) return
  if (taskDialog.completed_tasks === '' || taskDialog.completed_tasks < 0) return ElMessage.warning('请输入已完成订单数')
  submitLoading.value = true
  try {
    const res = await configUserTasks({
      user_id: taskDialog.user.id,
      task_amount: taskDialog.task_amount || 100,
      completed_tasks: taskDialog.completed_tasks,
      task_date: taskDialog.task_date,
    })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('已完成订单数已保存')
    taskDialog.visible = false
    loadUsers()
  } catch (error) {
    ElMessage.error(error.message || '订单配置失败')
  } finally {
    submitLoading.value = false
  }
}

const submitReset = async () => {
  if (!resetDialog.user) return
  if (!resetDialog.remark) return ElMessage.warning('请填写原因')
  submitLoading.value = true
  try {
    const res = await resetUserTasks({ user_id: resetDialog.user.id, remark: resetDialog.remark, task_date: resetDialog.task_date })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('重置成功')
    resetDialog.visible = false
    loadUsers()
  } catch (error) {
    ElMessage.error(error.message || '重置失败')
  } finally {
    submitLoading.value = false
  }
}

const loadComboList = async () => {
  if (!comboDialog.user) return
  try {
    const res = await getComboTasks({ user_id: comboDialog.user.id })
    if (res.data.code === 200) {
      comboDialog.comboList = res.data.data.list || []
    } else {
      ElMessage.warning(res.data.msg || '加载连单失败')
    }
  } catch (error) {
    ElMessage.error('加载连单失败')
  }
}

const reloadComboDialog = async () => {
  await loadComboList()
  if (comboDialog.task_amount) {
    await searchComboGoods()
  }
}

const calculateComboBalance = () => {
  const balance = Number(comboDialog.user?.balance || 0)
  const frozen = Number(comboDialog.user?.frozen_balance || 0)
  comboDialog.task_amount = Number((balance + frozen).toFixed(2))
}

const searchComboGoods = async () => {
  const targetAmount = Number(comboDialog.task_amount || 0)
  const maxDiff = Number(comboDialog.max_diff || 0)
  comboGoodsLoading.value = true
  try {
    const res = await getGoods({ page: 1, pageSize: 200, status: 1 })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    const list = res.data.data.list || []
    const goods = buildComboGoods(list, targetAmount, maxDiff)
    comboDialog.goods = goods
    comboDialog.packages = goods[0] ? [createComboPackage(goods[0], 0)] : []
    updateComboPackageTotal()
    if (!goods.length) {
      ElMessage.warning('没有匹配到商品')
    }
  } catch (error) {
    ElMessage.error(error.message || '搜索商品失败')
  } finally {
    comboGoodsLoading.value = false
  }
}

const updateComboPackageTotal = () => {
  comboDialog.task_amount = Number(comboDialog.packages.reduce((sum, item) => sum + Number(item.task_amount || 0), 0).toFixed(2))
}

const createComboPackage = (row, index) => {
  return {
    uid: `${row.id}-${Date.now()}-${index}`,
    good_id: row.id,
    good_name: row.name || '',
    price: Number(row.price || 0),
    quantity: Number(row.quantity || 1),
    task_amount: Number(row.total_price || row.price || 0),
    commission_rate: Number(comboDialog.commission_rate || 0),
  }
}

const removeComboPackage = (index) => {
  comboDialog.packages.splice(index, 1)
  updateComboPackageTotal()
}

const replaceComboPackage = (index) => {
  if (!comboDialog.goods.length) return ElMessage.warning('请先搜索商品')
  const currentId = comboDialog.packages[index]?.good_id
  const currentGoodsIndex = comboDialog.goods.findIndex((item) => Number(item.id) === Number(currentId))
  const nextIndex = currentGoodsIndex >= 0 ? (currentGoodsIndex + 1) % comboDialog.goods.length : 0
  comboDialog.packages.splice(index, 1, createComboPackage(comboDialog.goods[nextIndex], index))
  updateComboPackageTotal()
}

const submitCombo = async () => {
  if (!comboDialog.user) return
  if (!comboDialog.task_index || comboDialog.task_index <= 0) return ElMessage.warning('请输入第几单')
  if (!comboDialog.packages.length) return ElMessage.warning('请先搜索并选择商品')
  if (!comboDialog.commission_rate || comboDialog.commission_rate <= 0) return ElMessage.warning('请输入默认佣金')
  if (comboDialog.packages.some((item) => !item.commission_rate || item.commission_rate <= 0)) return ElMessage.warning('请填写每个包裹的佣金')
  updateComboPackageTotal()
  submitLoading.value = true
  try {
    const res = await saveComboTask({
      user_id: comboDialog.user.id,
      task_index: comboDialog.task_index,
      task_amount: comboDialog.task_amount,
      commission_rate: comboDialog.commission_rate,
      good_id: comboDialog.packages[0]?.good_id || 0,
      mode: 'append',
      packages: comboDialog.packages.map((item) => ({
        good_id: item.good_id,
        task_amount: item.task_amount,
        commission_rate: item.commission_rate,
      })),
    })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('连单已追加')
    comboDialog.packages = []
    comboDialog.task_amount = ''
    await loadComboList()
    loadUsers()
  } catch (error) {
    ElMessage.error(error.message || '连单保存失败')
  } finally {
    submitLoading.value = false
  }
}

const deleteCombo = async (row) => {
  try {
    const res = await deleteComboTask(row.item_id || row.package_item_id || row.id)
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('已删除')
    await loadComboList()
    loadUsers()
  } catch (error) {
    ElMessage.error(error.message || '删除失败')
  }
}

const toggleStatus = async (row) => {
  try {
    const res = await toggleUserStatus(row.id, row.status)
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success(row.status === 1 ? '账号已启用' : '账号已禁用')
  } catch (error) {
    row.status = row.status === 1 ? 0 : 1
    ElMessage.error(error.message || '更新失败')
  }
}

const openTasks = async (row) => {
  tasksDialog.user = row
  tasksDialog.page = 1
  tasksDialog.task_date = ''
  await loadTaskRecords()
}

const reloadTasks = () => {
  loadTaskRecords()
}

const handleTaskSizeChange = (val) => {
  tasksDialog.pageSize = val
  tasksDialog.page = 1
  loadTaskRecords()
}

const handleTaskCurrentChange = (val) => {
  tasksDialog.page = val
  loadTaskRecords()
}

const loadTaskRecords = async () => {
  if (!tasksDialog.user) return
  try {
    const params = { user_id: tasksDialog.user.id, page: tasksDialog.page, pageSize: tasksDialog.pageSize }
    if (tasksDialog.task_date) params.task_date = tasksDialog.task_date
    const res = await getUserTasks(params)
    if (res.data.code === 200) {
      tasksDialog.tasks = res.data.data.tasks || []
      tasksDialog.total = res.data.data.total || 0
      tasksDialog.visible = true
    }
  } catch (error) {
    ElMessage.error('加载任务记录失败')
  }
}

const openRecords = async (row) => {
  recordsDialog.user = row
  recordsDialog.page = 1
  await loadBalanceRecords()
}

const reloadRecords = () => {
  loadBalanceRecords()
}

const handleRecordSizeChange = (val) => {
  recordsDialog.pageSize = val
  recordsDialog.page = 1
  loadBalanceRecords()
}

const handleRecordCurrentChange = (val) => {
  recordsDialog.page = val
  loadBalanceRecords()
}

const loadBalanceRecords = async () => {
  if (!recordsDialog.user) return
  try {
    const res = await getBalanceRecords({ user_id: recordsDialog.user.id, page: recordsDialog.page, pageSize: recordsDialog.pageSize })
    if (res.data.code === 200) {
      recordsDialog.list = res.data.data.list || []
      recordsDialog.total = res.data.data.total || 0
      recordsDialog.visible = true
    }
  } catch (error) {
    ElMessage.error('加载账户明细失败')
  }
}

const createRecordFromDialog = () => {
  if (!recordsDialog.user) return
  openBalance(recordsDialog.user, 'deposit')
}

const exportRecords = () => {
  const header = ['#', '用户ID', '账号', '手机', '操作类型', '变动数量', '备注', '变动记录']
  const rows = recordsDialog.list.map((row, index) => [
    recordStartIndex.value + index,
    row.related_user?.id || row.user_id,
    row.related_user?.account || '',
    row.related_user?.phone || '',
    row.display_type || recordMeta(row.type).label,
    formatPlain(row.display_amount ?? Math.abs(Number(row.change_amount))),
    row.remark || '(未设置)',
    row.change_record || '',
  ])
  const csv = [header, ...rows].map((line) => line.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([`\ufeff${csv}`], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `balance-records-${recordsDialog.user?.id || 'user'}.csv`
  link.click()
  URL.revokeObjectURL(link.href)
}

const taskStatus = (row) => {
  const map = {
    0: { label: '未完成', type: 'info' },
    1: { label: '已完成', type: 'success' },
    2: { label: '已冻结', type: 'warning' },
  }
  return map[Number(row.status)] || map[0]
}

const recordMeta = (type) => {
  const map = {
    deposit: { label: '充值', type: 'success' },
    withdraw: { label: '提现', type: 'danger' },
    adjust: { label: '调整', type: 'warning' },
    task_commission: { label: '任务利润', type: 'primary' },
    linked_profit_share: { label: '关联分润', type: 'success' },
    reset_tasks: { label: '重置任务', type: 'info' },
    bonus: { label: '奖励', type: 'warning' },
  }
  return map[type] || { label: type || '未知', type: 'info' }
}

onMounted(() => {
  loadUsers()
  loadMemberLevels()
})
</script>

<style scoped>
.page {
  padding: 18px;
}

.member-card {
  border-radius: 4px;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.search-form {
  display: grid;
  grid-template-columns: repeat(3, minmax(180px, 1fr));
  gap: 24px;
}

.search-actions {
  display: flex;
  justify-content: center;
  gap: 10px;
  padding: 8px 0 16px;
  border-bottom: 1px solid #edf0f5;
}

.table-tip {
  margin: 14px 0 6px;
  color: #667085;
  font-size: 12px;
}

.member-table {
  width: 100%;
}

.member-table :deep(.el-table__cell) {
  vertical-align: top;
}

.multi-line {
  display: grid;
  gap: 3px;
  color: #52637a;
  font-size: 12px;
  line-height: 1.35;
}

.button-row,
.action-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.button-col {
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: flex-start;
}

.button-col .el-button {
  margin-left: 0;
}

.action-grid {
  align-items: center;
}

.positive {
  color: #168a45;
}

.negative {
  color: #d92d20;
}

.muted {
  color: #667085;
  font-size: 13px;
}

.basic-form {
  padding: 18px 22px 34px;
}

.pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}

.dialog-toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 18px;
}

.crumb {
  margin-left: auto;
  color: #667085;
  font-size: 13px;
}

.task-record-table {
  font-size: 13px;
}

.task-record-table :deep(.el-table__header th),
.balance-record-table :deep(.el-table__header th) {
  color: #3f4d5a;
  font-size: 15px;
  font-weight: 700;
}

.balance-record-table {
  font-size: 14px;
}

.record-user {
  font-weight: 600;
}

.level-edit-dialog :deep(.el-dialog__header) {
  padding: 24px;
  border-bottom: 1px solid #edf0f5;
}

.password-info-dialog :deep(.el-dialog__header) {
  padding: 24px 22px;
  border-bottom: 1px solid #edf0f5;
}

.password-info-dialog :deep(.el-dialog__title) {
  color: #738296;
  font-size: 22px;
  font-weight: 700;
}

.password-info-dialog :deep(.el-dialog__body) {
  padding: 26px 56px 34px;
}

.password-info-dialog :deep(.el-dialog__footer) {
  padding: 20px 22px;
  border-top: 1px solid #edf0f5;
}

.password-info-form :deep(.el-form-item__label) {
  color: #263642;
  font-size: 17px;
  font-weight: 700;
}

.password-info-form :deep(.el-input__wrapper) {
  min-height: 42px;
  border-radius: 0;
  box-shadow: 0 0 0 1px #dde3ea inset;
}

.password-info-form :deep(.is-disabled .el-input__wrapper) {
  background: #eeeeee;
  box-shadow: none;
}

.password-info-form :deep(.is-disabled .el-input__inner) {
  color: #6d7c8c;
  -webkit-text-fill-color: #6d7c8c;
}

.password-info-form .highlight-label :deep(.el-form-item__label) {
  color: #12a85a;
}

.password-info-form .highlight-label :deep(.el-input__wrapper) {
  box-shadow: 0 0 0 1px #19c46a inset;
}

.level-edit-dialog :deep(.el-dialog__title) {
  color: #738296;
  font-size: 22px;
  font-weight: 700;
}

.level-edit-dialog :deep(.el-dialog__body) {
  padding: 34px 44px 54px;
}

.level-edit-dialog :deep(.el-dialog__footer) {
  padding: 22px;
  border-top: 1px solid #edf0f5;
}

.level-edit-form {
  padding: 0 0 0;
}

.level-edit-form :deep(.el-form-item__label) {
  color: #263642;
  font-size: 18px;
  font-weight: 700;
  line-height: 1.2;
  margin-bottom: 10px;
}

.level-edit-form :deep(.el-select__wrapper) {
  min-height: 50px;
  border-radius: 0;
  box-shadow: 0 0 0 1px #dde3ea inset;
}

.recharge-dialog :deep(.el-dialog__header) {
  padding: 24px 24px;
  border-bottom: 1px solid #edf0f5;
}

.recharge-dialog :deep(.el-dialog__title) {
  color: #738296;
  font-size: 22px;
  font-weight: 700;
}

.recharge-dialog :deep(.el-dialog__body) {
  padding: 34px 44px 60px;
}

.recharge-user {
  display: grid;
  grid-template-columns: 1fr 1fr;
  align-items: center;
  min-height: 170px;
}

.avatar-placeholder {
  position: relative;
  width: 74px;
  height: 74px;
  margin-left: 76px;
  border: 4px solid #d7dbe1;
  border-radius: 50%;
  background: #f5f6f8;
  box-shadow: inset 0 0 0 3px #fff;
}

.avatar-head {
  position: absolute;
  top: 18px;
  left: 25px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #fff;
}

.avatar-body {
  position: absolute;
  right: 17px;
  bottom: 13px;
  left: 17px;
  height: 28px;
  border-radius: 50% 50% 42% 42%;
  background: #fff;
}

.recharge-user-info {
  color: #738296;
  font-size: 20px;
  font-weight: 700;
  line-height: 1.6;
}

.recharge-panel {
  margin-top: 34px;
  padding: 0 54px 58px;
  border: 1px solid #edf0f5;
  box-shadow: 0 1px 6px rgb(15 23 42 / 4%);
}

.recharge-panel h3 {
  margin: 0 -54px 58px;
  padding: 18px 26px;
  border-bottom: 1px solid #edf0f5;
  color: #263642;
  font-size: 18px;
}

.recharge-form {
  max-width: 704px;
  margin: 0 auto;
}

.recharge-form :deep(.el-form-item__label) {
  color: #263642;
  font-size: 18px;
  font-weight: 700;
}

.recharge-form :deep(.el-input__wrapper) {
  min-height: 50px;
  border-radius: 0;
  box-shadow: 0 0 0 1px #dde3ea inset;
}

.recharge-form :deep(.is-disabled .el-input__wrapper) {
  background: #eeeeee;
  box-shadow: none;
}

.recharge-form :deep(.is-disabled .el-input__inner) {
  color: #738296;
  -webkit-text-fill-color: #738296;
}

.recharge-form :deep(.el-textarea__inner) {
  min-height: 80px !important;
  border-radius: 0;
}

.recharge-form :deep(.el-radio__label) {
  color: #263642;
  font-size: 16px;
  font-weight: 700;
}

.recharge-submit {
  display: flex;
  justify-content: center;
  margin-top: 26px;
}

.recharge-submit .el-button {
  min-width: 74px;
  min-height: 48px;
  font-size: 18px;
  font-weight: 700;
}

.account-dialog :deep(.el-dialog__header) {
  padding: 24px 22px;
  border-bottom: 1px solid #edf0f5;
}

.account-dialog :deep(.el-dialog__title) {
  color: #738296;
  font-size: 22px;
}

.account-dialog :deep(.el-dialog__body) {
  padding: 26px 56px 34px;
}

.account-dialog :deep(.el-dialog__footer) {
  padding: 20px 22px;
  border-top: 1px solid #edf0f5;
}

.account-form :deep(.el-form-item__label) {
  color: #263642;
  font-size: 17px;
  font-weight: 700;
}

.account-form :deep(.el-input__wrapper) {
  min-height: 42px;
  border-radius: 0;
  box-shadow: 0 0 0 1px #dde3ea inset;
}

.account-form :deep(.is-disabled .el-input__wrapper) {
  background: #eeeeee;
  box-shadow: none;
}

.account-form :deep(.is-disabled .el-input__inner) {
  color: #6d7c8c;
  -webkit-text-fill-color: #6d7c8c;
}

.account-form .highlight-label :deep(.el-form-item__label) {
  color: #12a85a;
}

.account-form .highlight-label :deep(.el-input__wrapper) {
  box-shadow: 0 0 0 1px #19c46a inset;
}

.credit-dialog :deep(.el-dialog__header) {
  padding: 24px 30px;
  border-bottom: 1px solid #edf0f5;
}

.credit-dialog :deep(.el-dialog__title) {
  color: #738296;
  font-size: 26px;
  font-weight: 500;
}

.credit-dialog :deep(.el-dialog__headerbtn) {
  top: 20px;
  right: 24px;
  width: 42px;
  height: 42px;
  font-size: 28px;
}

.credit-dialog :deep(.el-dialog__body) {
  padding: 30px 44px 52px;
}

.credit-dialog :deep(.el-dialog__footer) {
  padding: 22px 24px;
  border-top: 1px solid #edf0f5;
}

.credit-form :deep(.el-form-item__label) {
  margin-bottom: 8px;
  color: #263642;
  font-size: 22px;
  font-weight: 700;
}

.credit-form :deep(.el-input__wrapper) {
  min-height: 50px;
  border-radius: 0;
  box-shadow: 0 0 0 1px #dde3ea inset;
}

.credit-form :deep(.el-input__inner) {
  color: #738296;
  font-size: 20px;
}

.credit-dialog :deep(.el-button) {
  min-height: 48px;
  padding: 0 16px;
  font-size: 20px;
}

.combo-dialog :deep(.el-dialog__body) {
  padding-top: 4px;
}

.combo-toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 18px;
  color: #6b7a88;
}

.combo-panel {
  min-height: 520px;
  padding: 44px 46px 34px;
  border: 1px solid #edf0f5;
  background: #fff;
  box-shadow: 0 2px 10px rgb(15 23 42 / 6%);
}

.combo-summary {
  display: grid;
  gap: 12px;
  margin-bottom: 18px;
  color: #738296;
  font-size: 14px;
}

.combo-summary strong {
  color: #2f3b4a;
  font-size: 15px;
}

.combo-form {
  max-width: none;
}

.combo-form :deep(.el-form-item) {
  margin-bottom: 10px;
}

.combo-form :deep(.el-input__wrapper) {
  border-radius: 0;
}

.form-help {
  width: 100%;
  margin-top: 6px;
  color: #6b7a88;
  font-size: 13px;
}

.combo-search-row {
  display: grid;
  grid-template-columns: minmax(360px, 1fr) 320px auto;
  gap: 28px;
  align-items: start;
}

.combo-search-row :deep(.el-form-item) {
  margin-bottom: 0;
}

.combo-product-table {
  margin-top: 18px;
}

.combo-product-table :deep(.selected-row) {
  background: #ecf5ff;
}

.combo-package-list {
  margin-top: 18px;
}

.combo-package-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
  color: #3f4d5a;
}

.combo-actions {
  display: flex;
  justify-content: center;
  gap: 8px;
  margin-top: 24px;
}

.combo-current-title {
  margin: 38px 0 4px;
  color: #738296;
  font-size: 30px;
  font-weight: 400;
}

.combo-list-table {
  font-size: 14px;
}

.combo-list-table :deep(.el-table__header th),
.combo-product-table :deep(.el-table__header th) {
  color: #3f4d5a;
  font-size: 15px;
  font-weight: 700;
}

@media (max-width: 900px) {
  .search-form {
    grid-template-columns: 1fr;
    gap: 0;
  }

  .combo-panel {
    padding: 24px 16px;
  }

  .combo-search-row {
    grid-template-columns: 1fr;
    gap: 10px;
  }
}
</style>
